<?php

namespace App\Services\HealthTalk;

use App\Helpers\LoggerHelper;
use App\Models\HealthTalkQaPair;
use App\Models\HealthTalkSession;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Turns a finished conversation into what Session History shows: a title,
 * a short summary, topic tags and the question/answer pairs.
 */
class SessionSummarizer
{
    private const LANGUAGES = [
        'ENG' => 'English',
        'HIN' => 'Hindi',
        'MAR' => 'Marathi',
        'BEN' => 'Bengali',
    ];

    private const SCHEMA = [
        'type' => 'OBJECT',
        'properties' => [
            'title' => ['type' => 'STRING'],
            'summary' => ['type' => 'STRING'],
            'tags' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
            'qa' => [
                'type' => 'ARRAY',
                'items' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'question' => ['type' => 'STRING'],
                        'answer' => ['type' => 'STRING'],
                    ],
                    'required' => ['question', 'answer'],
                ],
            ],
        ],
        'required' => ['title', 'summary', 'tags', 'qa'],
    ];

    /** Q&A pairs kept per conversation: its most important questions. */
    private const MAX_QA = 2;

    public function __construct(private VertexAiClient $vertex) {}

    /** Never throws; the outcome is recorded in summary_status. */
    public function summarize(HealthTalkSession $session): bool
    {
        $messages = $session->messages()->whereIn('role', ['user', 'assistant'])->get();

        if ($session->turn_count === 0 || $messages->where('role', 'user')->isEmpty()) {
            $session->forceFill(['summary_status' => 'skipped', 'modified_on' => Carbon::now()])->save();

            return false;
        }

        try {
            $result = $this->vertex->generateJson($this->prompt($session, $messages), self::SCHEMA);

            $title = trim((string) ($result['title'] ?? ''));
            $summary = trim((string) ($result['summary'] ?? ''));
            if ($title === '' || $summary === '') {
                throw new RuntimeException('Summary response was missing a title or summary');
            }
            $tags = array_values(array_slice(array_filter(array_map(
                fn ($t) => mb_substr(trim((string) $t), 0, 40),
                (array) ($result['tags'] ?? [])
            )), 0, 3));
            // Session History shows the summary plus the conversation's most
            // important questions, never more than MAX_QA (the full transcript
            // stays in ht_messages).
            $qa = array_slice(array_values(array_filter(
                (array) ($result['qa'] ?? []),
                fn ($p) => trim((string) ($p['question'] ?? '')) !== '' && trim((string) ($p['answer'] ?? '')) !== ''
            )), 0, self::MAX_QA);

            DB::transaction(function () use ($session, $title, $summary, $tags, $qa) {
                $now = Carbon::now();
                HealthTalkQaPair::where('session_id', $session->session_id)->delete();
                foreach ($qa as $i => $pair) {
                    HealthTalkQaPair::create([
                        'session_id' => $session->session_id,
                        'seq' => $i,
                        'question' => trim($pair['question']),
                        'answer' => trim($pair['answer']),
                        'created_on' => $now,
                    ]);
                }
                $session->forceFill([
                    'title' => mb_substr($title, 0, 255),
                    'summary' => $summary,
                    'tags' => $tags,
                    'summary_status' => 'ready',
                    'summary_error' => null,
                    'modified_on' => $now,
                ])->save();
            });

            return true;
        } catch (Throwable $e) {
            $session->forceFill([
                'summary_status' => 'failed',
                'summary_error' => mb_substr($e->getMessage(), 0, 1000),
                'modified_on' => Carbon::now(),
            ])->save();

            LoggerHelper::logCritical(
                message: 'HealthTalk session summary failed',
                context: __CLASS__,
                fn: __FUNCTION__,
                custom_data: ['session_id' => $session->session_id, 'exception' => $e, 'error_message' => $e->getMessage()]
            );

            return false;
        }
    }

    private function prompt(HealthTalkSession $session, $messages): string
    {
        $language = self::LANGUAGES[$session->language] ?? 'English';

        $transcript = $messages->map(function ($m) {
            if ($m->role === 'user') {
                return 'User'.($m->input_mode === 'voice' ? ' (spoken)' : '').': '.$m->content;
            }

            return 'Aira: '.$m->content.($m->interrupted ? ' [cut off by the user]' : '');
        })->implode("\n");

        return <<<PROMPT
        Below is a conversation between a user and Aira, Saathealth's voice assistant for health awareness and insurance (such as the Hospicash plan). The user will see your output in their conversation history.

        Write everything in {$language}.

        - title: 2 to 6 words naming the main topic, like a chat title.
        - summary: 2 or 3 short sentences addressed to the user ("You asked about..."), covering what they wanted and the key information Aira gave.
        - tags: 1 to 3 short topic labels (one or two words each).
        - qa: the 2 most important questions the user asked (fewer if they asked fewer), most important first. Important means what matters most to the user's health or insurance situation, such as coverage, claims, money or deadlines, not greetings, small talk or minor follow-ups. If they asked the same thing twice, count it once. "question" is the user's question rewritten as one clear sentence; "answer" is Aira's answer to it, condensed to its key facts. If Aira declined or did not know, say so in the answer.

        Rules:
        - Use only what is in the conversation. Never add facts, numbers or advice Aira did not give.
        - Spoken lines come from speech recognition and may contain small errors; fix obvious mis-hearings (for example of product names) without changing the meaning.
        - Plain text only, no markdown.

        Conversation:
        {$transcript}
        PROMPT;
    }
}
