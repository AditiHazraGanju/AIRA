<?php

namespace Modules\HealthTalk\Http\Controllers;

use App\Helpers\LoggerHelper;
use App\Http\Controllers\Controller;
use App\Models\HealthTalkMessage;
use App\Models\HealthTalkQaPair;
use App\Models\HealthTalkSession;
use App\Services\HealthTalk\SessionSummarizer;
use App\Services\ResponseService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Saved conversations with Aira.
 *
 * The HealthTalk relay writes them as they happen (store, messages, end),
 * using the user's own JWT. The chatbot reads them for Session History and
 * posts the end-of-conversation rating and per-answer feedback. A user only
 * ever sees or writes their own sessions.
 */
class SessionController extends Controller
{
    // A session the relay never closed (crash, redeploy) is treated as ended
    // after this long without activity. The relay itself caps sessions at
    // 15 minutes and closes idle ones after 2, so live ones never get here.
    private const STALE_AFTER_MINUTES = 20;

    /**
     * POST /api/v1/healthtalk/sessions  (relay)
     * Idempotent: retrying with the same session_uuid returns the same session.
     */
    public function store(Request $request)
    {
        try {
            $userId = $this->currentUserId();
            if (! $userId) {
                return ResponseService::error('Unauthorized, Access Denied', 401);
            }

            $validated = $request->validate([
                'session_uuid' => 'required|uuid',
                'language' => 'required|in:ENG,HIN,MAR,BEN',
            ]);

            $existing = HealthTalkSession::where('session_uuid', $validated['session_uuid'])->first();
            if ($existing) {
                if ($existing->sh_user_id !== $userId) {
                    return ResponseService::error('Session not found', 404);
                }

                return ResponseService::success('Session already exists', 200, ['session_id' => $existing->session_uuid]);
            }

            $now = Carbon::now();
            $session = HealthTalkSession::create([
                'session_uuid' => $validated['session_uuid'],
                'sh_user_id' => $userId,
                'language' => $validated['language'],
                'status' => 'active',
                'started_on' => $now,
                'last_activity_on' => $now,
                'created_on' => $now,
            ]);

            LoggerHelper::logInfo(
                message: 'HealthTalk session started',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['session_id' => $session->session_id, 'language' => $session->language]
            );

            return ResponseService::success('Session created', 201, ['session_id' => $session->session_uuid]);
        } catch (ValidationException $ve) {
            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            return $this->failure($e, __FUNCTION__, 'Failed to create session');
        }
    }

    /**
     * POST /api/v1/healthtalk/sessions/{uuid}/messages  (relay)
     * Appends one turn's messages. Re-sent seq numbers are ignored, so the
     * relay can safely retry a batch.
     */
    public function storeMessages(Request $request, string $uuid)
    {
        try {
            $session = $this->ownedSession($uuid);
            if (! $session) {
                return ResponseService::error('Session not found', 404);
            }

            $validated = $request->validate([
                'messages' => 'required|array|min:1|max:50',
                'messages.*.seq' => 'required|integer|min:0',
                'messages.*.role' => 'required|in:user,assistant,tool',
                'messages.*.content' => 'required|string|max:20000',
                'messages.*.input_mode' => 'nullable|in:voice,text',
                'messages.*.interrupted' => 'nullable|boolean',
                'messages.*.metadata' => 'nullable|array',
                'messages.*.at' => 'nullable|date',
            ]);

            $now = Carbon::now();
            DB::transaction(function () use ($session, $validated, $now) {
                DB::table('ht_messages')->insertOrIgnore(array_map(fn ($m) => [
                    'session_id' => $session->session_id,
                    'seq' => $m['seq'],
                    'role' => $m['role'],
                    'content' => $m['content'],
                    'input_mode' => $m['role'] === 'user' ? ($m['input_mode'] ?? null) : null,
                    'interrupted' => (bool) ($m['interrupted'] ?? false),
                    'metadata' => isset($m['metadata']) ? json_encode($m['metadata']) : null,
                    'created_on' => isset($m['at']) ? Carbon::parse($m['at']) : $now,
                ], $validated['messages']));

                $session->forceFill([
                    'turn_count' => HealthTalkMessage::where('session_id', $session->session_id)
                        ->where('role', 'user')->count(),
                    'last_activity_on' => $now,
                    'modified_on' => $now,
                ])->save();
            });

            return ResponseService::success('Messages saved', 200, ['turn_count' => $session->turn_count]);
        } catch (ValidationException $ve) {
            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            return $this->failure($e, __FUNCTION__, 'Failed to save messages');
        }
    }

    /**
     * POST /api/v1/healthtalk/sessions/{uuid}/end  (relay)
     * Marks the session ended and generates its summary after responding.
     */
    public function end(Request $request, string $uuid)
    {
        try {
            $session = $this->ownedSession($uuid);
            if (! $session) {
                return ResponseService::error('Session not found', 404);
            }

            $validated = $request->validate(['reason' => 'nullable|string|max:50']);

            if ($session->status !== 'ended') {
                $this->closeSession($session, $validated['reason'] ?? null);
            }

            return ResponseService::success('Session ended', 200);
        } catch (ValidationException $ve) {
            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            return $this->failure($e, __FUNCTION__, 'Failed to end session');
        }
    }

    /**
     * GET /api/v1/healthtalk/sessions?limit=10  (chatbot Session History)
     * The user's conversations with at least one question, newest first.
     */
    public function index(Request $request)
    {
        try {
            $userId = $this->currentUserId();
            if (! $userId) {
                return ResponseService::error('Unauthorized, Access Denied', 401);
            }

            $limit = min(max((int) $request->query('limit', 10), 1), 100);
            $this->closeStaleSessions($userId);

            $query = HealthTalkSession::where('sh_user_id', $userId)
                ->where('is_deleted', false)
                ->where('turn_count', '>', 0);

            $total = (clone $query)->count();
            $sessions = $query->orderByDesc('started_on')->limit($limit)->get();

            return ResponseService::success($sessions->count().' sessions retrieved', 200, [
                'sessions' => $sessions->map(fn ($s) => $this->formatSession($s))->values(),
                'total' => $total,
            ]);
        } catch (Exception $e) {
            return $this->failure($e, __FUNCTION__, 'Failed to retrieve sessions');
        }
    }

    /**
     * GET /api/v1/healthtalk/sessions/{uuid}  (chatbot session details)
     */
    public function show(string $uuid)
    {
        try {
            $session = $this->ownedSession($uuid);
            if (! $session || $session->is_deleted) {
                return ResponseService::error('Session not found', 404);
            }

            $session->load(['qaPairs', 'messages']);

            return ResponseService::success('Session retrieved', 200, [
                'session' => $this->formatSession($session) + [
                    'qa' => $session->qaPairs->map(fn ($qa) => [
                        'id' => $qa->qa_id,
                        'question' => $qa->question,
                        'answer' => $qa->answer,
                        'feedback' => $qa->feedback,
                    ])->values(),
                    // The raw transcript, for when the summary isn't ready (or failed).
                    'messages' => $session->messages
                        ->whereIn('role', ['user', 'assistant'])
                        ->map(fn ($m) => [
                            'role' => $m->role,
                            'content' => $m->content,
                            'interrupted' => $m->interrupted,
                            'at' => optional($m->created_on)->toIso8601String(),
                        ])->values(),
                ],
            ]);
        } catch (Exception $e) {
            return $this->failure($e, __FUNCTION__, 'Failed to retrieve session');
        }
    }

    /**
     * POST /api/v1/healthtalk/sessions/{uuid}/rating  (EndConversationModal)
     */
    public function rate(Request $request, string $uuid)
    {
        try {
            $session = $this->ownedSession($uuid);
            if (! $session) {
                return ResponseService::error('Session not found', 404);
            }

            $validated = $request->validate(['rating' => 'required|integer|min:1|max:5']);

            $session->forceFill([
                'rating' => $validated['rating'],
                'rated_on' => Carbon::now(),
                'modified_on' => Carbon::now(),
            ])->save();

            return ResponseService::success('Thank you for your feedback', 200);
        } catch (ValidationException $ve) {
            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            return $this->failure($e, __FUNCTION__, 'Failed to save rating');
        }
    }

    /**
     * POST /api/v1/healthtalk/sessions/{uuid}/qa/{qaId}/feedback
     * Session History's "approve response" / "edit response" buttons.
     * feedback: approved | rejected | null (to clear)
     */
    public function qaFeedback(Request $request, string $uuid, int $qaId)
    {
        try {
            $session = $this->ownedSession($uuid);
            if (! $session) {
                return ResponseService::error('Session not found', 404);
            }

            $validated = $request->validate(['feedback' => 'nullable|in:approved,rejected']);

            $qa = HealthTalkQaPair::where('session_id', $session->session_id)->find($qaId);
            if (! $qa) {
                return ResponseService::error('Answer not found', 404);
            }

            $qa->forceFill([
                'feedback' => $validated['feedback'] ?? null,
                'feedback_on' => isset($validated['feedback']) ? Carbon::now() : null,
            ])->save();

            return ResponseService::success('Feedback saved', 200, ['feedback' => $qa->feedback]);
        } catch (ValidationException $ve) {
            return ResponseService::error('Validation error', 422, ['errors' => $ve->errors()]);
        } catch (Exception $e) {
            return $this->failure($e, __FUNCTION__, 'Failed to save feedback');
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** users.id of the caller, from the JWT JwtMiddleware already validated. */
    private function currentUserId(): ?int
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (Throwable) {
            return null;
        }

        return $user ? (int) $user->id : null;
    }

    /** The session, if it exists and belongs to the caller. */
    private function ownedSession(string $uuid): ?HealthTalkSession
    {
        $userId = $this->currentUserId();
        if (! $userId || ! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
            return null;
        }

        return HealthTalkSession::where('session_uuid', $uuid)->where('sh_user_id', $userId)->first();
    }

    private function closeSession(HealthTalkSession $session, ?string $reason): void
    {
        $session->forceFill([
            'status' => 'ended',
            'ended_on' => Carbon::now(),
            'end_reason' => $reason,
            'modified_on' => Carbon::now(),
        ])->save();

        $sessionId = $session->session_id;
        // Summaries take a few seconds of Gemini time; never make the caller wait.
        dispatch(function () use ($sessionId) {
            $session = HealthTalkSession::find($sessionId);
            if ($session) {
                app(SessionSummarizer::class)->summarize($session);
            }
        })->afterResponse();
    }

    private function closeStaleSessions(int $userId): void
    {
        HealthTalkSession::where('sh_user_id', $userId)
            ->where('status', 'active')
            ->where('last_activity_on', '<', Carbon::now()->subMinutes(self::STALE_AFTER_MINUTES))
            ->get()
            ->each(fn ($s) => $this->closeSession($s, 'abandoned'));
    }

    private function formatSession(HealthTalkSession $s): array
    {
        return [
            'id' => $s->session_uuid,
            'title' => $s->title,
            'summary' => $s->summary,
            'tags' => $s->tags ?? [],
            'language' => $s->language,
            'status' => $s->status,
            'summary_status' => $s->summary_status,
            'turn_count' => $s->turn_count,
            'rating' => $s->rating,
            'started_at' => optional($s->started_on)->toIso8601String(),
            'ended_at' => optional($s->ended_on)->toIso8601String(),
        ];
    }

    private function failure(Exception $e, string $fn, string $message)
    {
        LoggerHelper::logCritical(
            message: 'Exception occurred',
            context: __CLASS__,
            fn: $fn,
            custom_data: ['exception' => $e, 'error_message' => $e->getMessage()]
        );

        return ResponseService::error($message, 500, ['error' => $e->getMessage()]);
    }
}
