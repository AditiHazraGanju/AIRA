<?php

namespace Modules\HealthTalk\Http\Controllers;

use App\Helpers\LoggerHelper;
use App\Http\Controllers\Controller;
use App\Models\HealthTalkSession;
use App\Services\ResponseService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admin reporting on conversations with Aira: an overview (volumes, ratings,
 * answer feedback, unanswered questions), a conversations table, a single
 * conversation's transcript, and the answers users marked as wrong.
 *
 * Admin panel only (HealthTalkAdminOnly). Users are identified by id and
 * name; mobile numbers are deliberately never returned.
 */
class ReportController extends Controller
{
    private const TZ = 'Asia/Kolkata';

    /**
     * GET /api/v1/healthtalk/admin/overview?date_range=Y-m-d to Y-m-d
     */
    public function overview(Request $request)
    {
        try {
            [$from, $to] = $this->dateRangeIst($request->input('date_range'));
            $between = [$from->toIso8601String(), $to->toIso8601String()];

            $sessions = fn () => DB::table('ht_sessions')
                ->where('is_deleted', false)
                ->where('turn_count', '>', 0)
                ->whereBetween('started_on', $between);

            $totals = $sessions()->selectRaw('
                    COUNT(*) AS conversations,
                    COUNT(DISTINCT sh_user_id) AS users,
                    ROUND(AVG(turn_count)::numeric, 1) AS avg_turns,
                    ROUND(AVG(EXTRACT(EPOCH FROM (ended_on - started_on))) FILTER (WHERE ended_on IS NOT NULL)) AS avg_duration_seconds,
                    ROUND(AVG(rating)::numeric, 2) AS avg_rating,
                    COUNT(rating) AS rated
                ')->first();

            $ratings = $sessions()->whereNotNull('rating')
                ->selectRaw('rating, COUNT(*) AS n')->groupBy('rating')->pluck('n', 'rating');

            $languages = $sessions()->selectRaw('language, COUNT(*) AS n')
                ->groupBy('language')->orderByDesc('n')->pluck('n', 'language');

            $perDay = $sessions()
                ->selectRaw("(started_on AT TIME ZONE '".self::TZ."')::date AS day, COUNT(*) AS n")
                ->groupBy('day')->pluck('n', 'day');
            $daily = [];
            foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
                $key = $day->format('Y-m-d');
                $daily[] = ['date' => $key, 'count' => (int) ($perDay[$key] ?? 0)];
            }

            $sessionIds = $sessions()->select('session_id');

            $feedback = DB::table('ht_qa_pairs')
                ->whereIn('session_id', $sessionIds)
                ->whereNotNull('feedback')
                ->selectRaw('feedback, COUNT(*) AS n')->groupBy('feedback')->pluck('n', 'feedback');

            $lookups = DB::table('ht_messages')
                ->whereIn('session_id', $sessionIds)
                ->where('role', 'tool')
                ->selectRaw("COUNT(*) AS total, COUNT(*) FILTER (WHERE (metadata->>'results')::int = 0) AS no_result")
                ->first();

            // Searches that found nothing: what the Content Library is missing.
            // Aira writes these queries in English, whatever language the user spoke.
            $unanswered = DB::table('ht_messages')
                ->whereIn('session_id', $sessionIds)
                ->where('role', 'tool')
                ->whereRaw("(metadata->>'results')::int = 0")
                ->selectRaw('LOWER(TRIM(content)) AS query, COUNT(*) AS times, MAX(created_on) AS last_asked')
                ->groupBy('query')->orderByDesc('times')->orderByDesc('last_asked')
                ->limit(10)->get()
                ->map(fn ($r) => [
                    'query' => $r->query,
                    'times' => (int) $r->times,
                    'last_asked' => Carbon::parse($r->last_asked)->toIso8601String(),
                ]);

            $topTags = DB::table(DB::raw('ht_sessions s, jsonb_array_elements_text(s.tags) AS tag'))
                ->whereIn('s.session_id', $sessionIds)
                ->selectRaw('tag, COUNT(*) AS n')->groupBy('tag')->orderByDesc('n')
                ->limit(10)->pluck('n', 'tag');

            return ResponseService::success('HealthTalk overview retrieved', 200, [
                'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'totals' => [
                    'conversations' => (int) $totals->conversations,
                    'users' => (int) $totals->users,
                    'avg_turns' => $totals->avg_turns !== null ? (float) $totals->avg_turns : null,
                    'avg_duration_seconds' => $totals->avg_duration_seconds !== null ? (int) $totals->avg_duration_seconds : null,
                    'avg_rating' => $totals->avg_rating !== null ? (float) $totals->avg_rating : null,
                    'rated' => (int) $totals->rated,
                    'approved_answers' => (int) ($feedback['approved'] ?? 0),
                    'rejected_answers' => (int) ($feedback['rejected'] ?? 0),
                    'lookups' => (int) $lookups->total,
                    'lookups_no_result' => (int) $lookups->no_result,
                ],
                'rating_distribution' => collect(range(1, 5))->mapWithKeys(fn ($r) => [$r => (int) ($ratings[$r] ?? 0)]),
                'languages' => $languages->map(fn ($n) => (int) $n),
                'daily' => $daily,
                'top_tags' => $topTags->map(fn ($n) => (int) $n),
                'unanswered' => $unanswered->values(),
            ]);
        } catch (Exception $e) {
            return $this->failure($e, __FUNCTION__, 'Failed to build HealthTalk overview');
        }
    }

    /**
     * POST /api/v1/healthtalk/admin/sessions/datatable  (DataTables server-side)
     * Filters: date_range, language, rating (1-5 | "unrated"), disliked_only
     */
    public function sessionsDatatable(Request $request)
    {
        try {
            $rejected = DB::table('ht_qa_pairs')->selectRaw('COUNT(*)')
                ->whereColumn('ht_qa_pairs.session_id', 's.session_id')->where('feedback', 'rejected');

            $query = DB::table('ht_sessions AS s')
                ->leftJoin('users AS u', 'u.id', '=', 's.sh_user_id')
                ->where('s.is_deleted', false)
                ->where('s.turn_count', '>', 0)
                ->select([
                    's.session_uuid', 's.sh_user_id', 'u.name AS user_name', 's.language', 's.title',
                    's.turn_count', 's.rating', 's.summary_status', 's.status', 's.started_on', 's.ended_on',
                ])
                ->selectSub($rejected, 'rejected_answers');

            $recordsTotal = (clone $query)->count();

            if ($range = $request->input('date_range')) {
                [$from, $to] = $this->dateRange($range);
                $query->whereBetween('s.started_on', [$from, $to]);
            }
            if (in_array($request->input('language'), ['ENG', 'HIN', 'MAR', 'BEN'], true)) {
                $query->where('s.language', $request->input('language'));
            }
            $rating = $request->input('rating');
            if ($rating === 'unrated') {
                $query->whereNull('s.rating');
            } elseif (in_array((string) $rating, ['1', '2', '3', '4', '5'], true)) {
                $query->where('s.rating', (int) $rating);
            }
            if ($request->boolean('disliked_only')) {
                $query->whereExists(fn ($q) => $q->from('ht_qa_pairs')
                    ->whereColumn('ht_qa_pairs.session_id', 's.session_id')->where('feedback', 'rejected'));
            }
            if ($search = trim((string) $request->input('search.value'))) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(fn ($q) => $q->where('s.title', 'ILIKE', $like)
                    ->orWhere('s.summary', 'ILIKE', $like)
                    ->orWhere('u.name', 'ILIKE', $like)
                    ->orWhereRaw('CAST(s.sh_user_id AS TEXT) = ?', [$search]));
            }

            $recordsFiltered = (clone $query)->count();

            // Column order must match the admin table.
            $columns = [1 => 's.started_on', 2 => 'u.name', 3 => 's.language', 4 => 's.title',
                5 => 's.turn_count', 6 => 's.rating', 7 => 'rejected_answers'];
            $orderIndex = (int) $request->input('order.0.column', 1);
            $orderDir = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($columns[$orderIndex] ?? 's.started_on', $orderDir);

            $length = (int) $request->input('length', 25);
            if ($length !== -1) {
                $query->skip((int) $request->input('start', 0))->take(min(max($length, 1), 200));
            }

            $rows = $query->get()->map(fn ($r) => [
                'id' => $r->session_uuid,
                'user_id' => $r->sh_user_id,
                'user_name' => $r->user_name,
                'language' => $r->language,
                'title' => $r->title,
                'turns' => (int) $r->turn_count,
                'rating' => $r->rating !== null ? (int) $r->rating : null,
                'rejected_answers' => (int) $r->rejected_answers,
                'summary_status' => $r->summary_status,
                'status' => $r->status,
                'started_at' => Carbon::parse($r->started_on)->toIso8601String(),
                'duration_seconds' => $r->ended_on ? Carbon::parse($r->started_on)->diffInSeconds(Carbon::parse($r->ended_on)) : null,
            ]);

            return response()->json([
                'draw' => (int) $request->input('draw'),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ]);
        } catch (Exception $e) {
            return $this->failure($e, __FUNCTION__, 'Failed to load conversations');
        }
    }

    /**
     * GET /api/v1/healthtalk/admin/sessions/{uuid}
     * Full transcript (including Content Library lookups), Q&A with feedback.
     */
    public function session(string $uuid)
    {
        try {
            if (! preg_match('/^[0-9a-f-]{36}$/i', $uuid)) {
                return ResponseService::error('Conversation not found', 404);
            }

            $session = HealthTalkSession::with(['messages', 'qaPairs'])->where('session_uuid', $uuid)->first();
            if (! $session) {
                return ResponseService::error('Conversation not found', 404);
            }

            $userName = DB::table('users')->where('id', $session->sh_user_id)->value('name');

            return ResponseService::success('Conversation retrieved', 200, ['session' => [
                'id' => $session->session_uuid,
                'user_id' => $session->sh_user_id,
                'user_name' => $userName,
                'language' => $session->language,
                'status' => $session->status,
                'end_reason' => $session->end_reason,
                'title' => $session->title,
                'summary' => $session->summary,
                'tags' => $session->tags ?? [],
                'summary_status' => $session->summary_status,
                'summary_error' => $session->summary_error,
                'rating' => $session->rating,
                'turns' => $session->turn_count,
                'started_at' => optional($session->started_on)->toIso8601String(),
                'ended_at' => optional($session->ended_on)->toIso8601String(),
                'messages' => $session->messages->map(fn ($m) => [
                    'role' => $m->role,
                    'content' => $m->content,
                    'input_mode' => $m->input_mode,
                    'interrupted' => $m->interrupted,
                    'metadata' => $m->metadata,
                    'at' => optional($m->created_on)->toIso8601String(),
                ])->values(),
                'qa' => $session->qaPairs->map(fn ($q) => [
                    'question' => $q->question,
                    'answer' => $q->answer,
                    'feedback' => $q->feedback,
                ])->values(),
            ]]);
        } catch (Exception $e) {
            return $this->failure($e, __FUNCTION__, 'Failed to load conversation');
        }
    }

    /**
     * POST /api/v1/healthtalk/admin/feedback/datatable  (DataTables server-side)
     * Answers users marked "edit response" (rejected), newest first.
     */
    public function feedbackDatatable(Request $request)
    {
        try {
            $query = DB::table('ht_qa_pairs AS q')
                ->join('ht_sessions AS s', 's.session_id', '=', 'q.session_id')
                ->leftJoin('users AS u', 'u.id', '=', 's.sh_user_id')
                ->where('q.feedback', 'rejected')
                ->where('s.is_deleted', false)
                ->select(['q.question', 'q.answer', 'q.feedback_on', 's.session_uuid', 's.language', 's.sh_user_id', 'u.name AS user_name']);

            $recordsTotal = (clone $query)->count();

            if ($range = $request->input('date_range')) {
                [$from, $to] = $this->dateRange($range);
                $query->whereBetween('q.feedback_on', [$from, $to]);
            }
            if (in_array($request->input('language'), ['ENG', 'HIN', 'MAR', 'BEN'], true)) {
                $query->where('s.language', $request->input('language'));
            }
            if ($search = trim((string) $request->input('search.value'))) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(fn ($q) => $q->where('q.question', 'ILIKE', $like)->orWhere('q.answer', 'ILIKE', $like));
            }

            $recordsFiltered = (clone $query)->count();

            $columns = [0 => 'q.feedback_on', 1 => 'q.question', 3 => 's.language'];
            $orderIndex = (int) $request->input('order.0.column', 0);
            $orderDir = $request->input('order.0.dir') === 'asc' ? 'asc' : 'desc';
            $query->orderBy($columns[$orderIndex] ?? 'q.feedback_on', $orderDir);

            $length = (int) $request->input('length', 25);
            if ($length !== -1) {
                $query->skip((int) $request->input('start', 0))->take(min(max($length, 1), 200));
            }

            $rows = $query->get()->map(fn ($r) => [
                'question' => $r->question,
                'answer' => $r->answer,
                'language' => $r->language,
                'session_id' => $r->session_uuid,
                'user_id' => $r->sh_user_id,
                'user_name' => $r->user_name,
                'feedback_at' => $r->feedback_on ? Carbon::parse($r->feedback_on)->toIso8601String() : null,
            ]);

            return response()->json([
                'draw' => (int) $request->input('draw'),
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => $recordsFiltered,
                'data' => $rows,
            ]);
        } catch (Exception $e) {
            return $this->failure($e, __FUNCTION__, 'Failed to load answer feedback');
        }
    }

    /**
     * "Y-m-d to Y-m-d" (or one day) as full IST days, formatted with their
     * UTC offset. The offset matters: a bare "Y-m-d H:i:s" would be read in
     * the DB session's timezone, shifting the window. Defaults to the last
     * 30 days.
     *
     * @return array{0: string, 1: string}
     */
    private function dateRange(?string $range): array
    {
        [$from, $to] = $this->dateRangeIst($range);

        return [$from->toIso8601String(), $to->toIso8601String()];
    }

    /** @return array{0: Carbon, 1: Carbon} start and end of day, in IST */
    private function dateRangeIst(?string $range): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(' to ', (string) $range))));

        try {
            $from = isset($parts[0]) ? Carbon::parse($parts[0], self::TZ) : Carbon::now(self::TZ)->subDays(29);
            $to = isset($parts[1]) ? Carbon::parse($parts[1], self::TZ) : (isset($parts[0]) ? $from->copy() : Carbon::now(self::TZ));
        } catch (Exception) {
            $from = Carbon::now(self::TZ)->subDays(29);
            $to = Carbon::now(self::TZ);
        }

        return [$from->startOfDay(), $to->endOfDay()];
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
