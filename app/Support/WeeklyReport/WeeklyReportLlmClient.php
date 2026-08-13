<?php

namespace App\Support\WeeklyReport;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Gọi LLM (OpenAI / Anthropic / Gemini / OpenAI-compatible) để viết lại
 * văn bản báo cáo tuần. Không bịa tên task — chỉ diễn đạt lại draft heuristic.
 */
class WeeklyReportLlmClient
{
    public function isConfigured(): bool
    {
        return (bool) config('weekly_report.llm.enabled')
            && filled(config('weekly_report.llm.api_key'));
    }

    public function provider(): string
    {
        return (string) config('weekly_report.llm.provider', 'openai');
    }

    public function model(): string
    {
        return (string) config('weekly_report.llm.model', 'gpt-4o-mini');
    }

    /**
     * @return array{executive: string, insight: string, sections: array<string, string>, outcomes: array<int, array{title: string, value: string}>}
     */
    public function rewrite(WeeklyReportContext $context, GeneratedReport $draft): array
    {
        $raw = $this->chat($this->systemPrompt(), $this->userPrompt($context, $draft));

        return $this->parseNarrative($raw);
    }

    private function chat(string $system, string $user): string
    {
        $provider = $this->provider();
        $timeout = max(10, (int) config('weekly_report.llm.timeout', 40));
        if ($provider === 'nvidia') {
            $timeout = max($timeout, 60);
        }

        try {
            $text = match ($provider) {
                'anthropic' => $this->chatAnthropic($system, $user, $timeout),
                'gemini' => $this->chatGemini($system, $user, $timeout),
                'openai_compatible', 'nvidia' => $this->chatOpenAi($system, $user, $timeout, compatible: true),
                default => $this->chatOpenAi($system, $user, $timeout, compatible: false),
            };
        } catch (RequestException $e) {
            Log::warning('Weekly report LLM HTTP error', [
                'provider' => $provider,
                'status' => $e->response?->status(),
            ]);
            throw new RuntimeException('LLM HTTP error', 0, $e);
        }

        if (! filled($text)) {
            throw new RuntimeException('LLM returned empty content');
        }

        return $text;
    }

    private function chatOpenAi(string $system, string $user, int $timeout, bool $compatible): string
    {
        $url = $this->chatCompletionsUrl($compatible);

        $payload = [
            'model' => $this->model(),
            'temperature' => 0.3,
            'max_tokens' => 4096,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
        ];
        if (! $compatible) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        if ($this->provider() === 'nvidia') {
            // Nemotron thinking làm rỗng `content` — tắt để lấy JSON ổn định.
            $payload['chat_template_kwargs'] = ['enable_thinking' => false];
        }

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withToken((string) config('weekly_report.llm.api_key'))
            ->post($url, $payload)
            ->throw();

        $json = $response->json();
        $content = trim((string) data_get($json, 'choices.0.message.content', ''));
        if ($content === '') {
            $content = trim((string) data_get($json, 'choices.0.message.reasoning_content', ''));
        }

        return $content;
    }

    /**
     * OpenAI SDK dùng base_url đã gồm `/v1` (NVIDIA: integrate.api.nvidia.com/v1).
     * Không nối thêm `/v1` nếu đã có, tránh `/v1/v1/chat/completions`.
     */
    private function chatCompletionsUrl(bool $compatible): string
    {
        $base = rtrim((string) config('weekly_report.llm.base_url'), '/');
        $provider = $this->provider();

        if ($base === '') {
            $base = $provider === 'nvidia'
                ? 'https://integrate.api.nvidia.com/v1'
                : 'https://api.openai.com/v1';
        }

        if ($compatible && $provider !== 'nvidia' && ! filled(config('weekly_report.llm.base_url'))) {
            throw new RuntimeException('openai_compatible requires base_url');
        }

        if (preg_match('#/v\d+[a-z]*$#i', $base)) {
            return $base.'/chat/completions';
        }

        return $base.'/v1/chat/completions';
    }

    private function chatAnthropic(string $system, string $user, int $timeout): string
    {
        $base = rtrim((string) config('weekly_report.llm.base_url'), '/') ?: 'https://api.anthropic.com';

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'x-api-key' => (string) config('weekly_report.llm.api_key'),
                'anthropic-version' => '2023-06-01',
            ])
            ->post($base.'/v1/messages', [
                'model' => $this->model(),
                'max_tokens' => 4096,
                'temperature' => 0.3,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $user],
                ],
            ])
            ->throw();

        return (string) data_get($response->json(), 'content.0.text', '');
    }

    private function chatGemini(string $system, string $user, int $timeout): string
    {
        $base = rtrim((string) config('weekly_report.llm.base_url'), '/')
            ?: 'https://generativelanguage.googleapis.com';
        $model = rawurlencode($this->model());

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders([
                'x-goog-api-key' => (string) config('weekly_report.llm.api_key'),
            ])
            ->post("{$base}/v1beta/models/{$model}:generateContent", [
                'systemInstruction' => [
                    'parts' => [['text' => $system]],
                ],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $user]]],
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'responseMimeType' => 'application/json',
                ],
            ])
            ->throw();

        return (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Bạn là trợ lý viết báo cáo tuần điều hành cho ban lãnh đạo trường học (tiếng Việt, súc tích, có số liệu).
Nhiệm vụ: ĐỌC nội dung task (tiêu đề, mô tả, ghi chú hoàn thành) rồi tổng hợp KẾT QUẢ mang lại — không chỉ đếm số hạng mục.
- KPI (sprint_progress, week_completed, week_story_points, worklog_hours…) là số liệu nguồn sự thật: giữ nguyên, không bịa thêm số.
- Thẻ «result»: mỗi việc hoàn thành trong tuần 1 dòng «Tên — giá trị giao được» (rút từ mô tả/ghi chú; không mô tả thì dùng tiêu đề).
- «outcomes»: tối đa 8 mục {title, value} — value = 1 câu giá trị (đã làm được gì, cho ai, xong tới đâu).
- Cấm bịa tên task, số liệu, người, ngày không có trong dữ liệu.
Trả về DUY NHẤT một JSON object (không markdown):
{"executive":"string","insight":"string","outcomes":[{"title":"string","value":"string"}],"sections":{"result":"string","current":"string","next":"string","risk":"string","feedback":"string","activity":"string"}}
PROMPT;
    }

    private function userPrompt(WeeklyReportContext $context, GeneratedReport $draft): string
    {
        $facts = [
            'project' => $context->project->name,
            'sprint' => $context->sprintLabel(),
            'week' => $context->weekNumber,
            'period' => $context->periodLabel(),
            'kpi' => $draft->kpi,
            'tasks' => WeeklyReportTaskFacts::digest($context),
            'draft' => [
                'executive' => $draft->executiveSummary,
                'insight' => $draft->aiSummary,
                'sections' => $draft->sections,
                'outcomes' => $draft->meta['outcomes'] ?? [],
            ],
        ];

        return "Đọc nội dung task và tổng hợp kết quả tuần (KPI + giá trị giao được):\n"
            .json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{executive: string, insight: string, sections: array<string, string>, outcomes: array<int, array{title: string, value: string}>}
     */
    private function parseNarrative(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $raw, $m)) {
            $raw = trim($m[1]);
        }

        $data = json_decode($raw, true);
        if (! is_array($data) && preg_match('/\{.*\}/s', $raw, $m)) {
            $data = json_decode($m[0], true);
        }
        if (! is_array($data)) {
            throw new RuntimeException('LLM returned invalid JSON');
        }

        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];

        return [
            'executive' => trim((string) ($data['executive'] ?? '')),
            'insight' => trim((string) ($data['insight'] ?? '')),
            'outcomes' => $this->parseOutcomes($data['outcomes'] ?? []),
            'sections' => [
                'result' => trim((string) ($sections['result'] ?? '')),
                'current' => trim((string) ($sections['current'] ?? '')),
                'next' => trim((string) ($sections['next'] ?? '')),
                'risk' => trim((string) ($sections['risk'] ?? '')),
                'feedback' => trim((string) ($sections['feedback'] ?? '')),
                'activity' => trim((string) ($sections['activity'] ?? '')),
            ],
        ];
    }

    /**
     * @return array<int, array{title: string, value: string}>
     */
    private function parseOutcomes(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($title === '' || $value === '') {
                continue;
            }
            $out[] = [
                'title' => mb_substr($title, 0, 180),
                'value' => mb_substr($value, 0, 400),
            ];
            if (count($out) >= 8) {
                break;
            }
        }

        return $out;
    }
}
