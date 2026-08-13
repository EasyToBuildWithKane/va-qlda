<?php

namespace App\Support\WeeklyReport;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Gọi LLM (OpenAI / Anthropic / Gemini / OpenAI-compatible) để tổng hợp
 * báo cáo tuần theo kết quả nghiệp vụ — không liệt kê lại task kỹ thuật.
 */
class WeeklyReportLlmClient
{
    public const JSON_OUTPUT_CONTRACT = <<<'PROMPT'
## ĐẦU RA BẮT BUỘC
Trả về DUY NHẤT một JSON object (không markdown fence, không giải thích, không heading ngoài JSON):
{"executive":"string","insight":"string","outcomes":[{"title":"string","value":"string"}],"sections":{"result":"string","current":"string","next":"string","risk":"string","feedback":"string","activity":"string"}}
Mọi giá trị string là văn bản thuần tiếng Việt. CẤM kí hiệu AI/markdown: ** ## ` * [] → emoji thẻ HTML <think>. Mỗi ý một dòng; không gạch đầu dòng, không đánh số (giao diện đã có bullet).
PROMPT;

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
        $custom = trim((string) config('weekly_report.llm.system_prompt', ''));
        $body = $custom !== '' ? $custom : $this->defaultWritingPrompt();

        return rtrim($body)."\n\n".self::JSON_OUTPUT_CONTRACT;
    }

    private function defaultWritingPrompt(): string
    {
        return <<<'PROMPT'
Bạn là Team Leader/PM chuyên tổng hợp báo cáo công việc phần mềm cho cấp quản lý (tiếng Việt, súc tích, có số liệu).

Tôi sẽ cung cấp dữ liệu thô gồm tasks hoàn thành, KPI, thành viên. Hãy phân tích và viết lại thành báo cáo KPI ngắn gọn, dễ hiểu, có giá trị quản lý — người không có chuyên môn IT vẫn đọc và hiểu ngay.

## NGUYÊN TẮC BẮT BUỘC

### 1. Viết theo "KẾT QUẢ", không viết theo "TASK"
KHÔNG: "Hoàn thành API, schema, audit log cho chức năng rút học bạ."
CÓ: "Hoàn thiện quy trình rút học bạ điện tử, giúp theo dõi đầy đủ từ lúc yêu cầu đến khi in và bàn giao."

KHÔNG: "Xây dựng bảng student_class_history."
CÓ: "Hoàn thiện quản lý lịch sử chuyển lớp, đảm bảo theo dõi được học sinh đã chuyển từ lớp nào sang lớp nào."

### 2. LUÔN TRẢ LỜI 3 CÂU HỎI
- ĐÃ LÀM ĐƯỢC GÌ? Kết quả cụ thể, giá trị mang lại.
- HIỆN TẠI RA SAO? Đúng tiến độ không? Hệ thống ổn định không?
- TIẾP THEO LÀ GÌ? Mốc kết quả sắp đạt, thời hạn.

### 3. QUY TẮC NGÔN NGỮ (tự động chuyển)
API: Kết nối/xử lý dữ liệu | Backend: Phần xử lý hệ thống | Frontend/UI: Giao diện sử dụng | Database/Schema: Cấu trúc/lưu trữ dữ liệu | Audit log: Lịch sử truy vết thay đổi | Permission: Phân quyền sử dụng | Bug: Lỗi hệ thống | Deploy: Đưa phiên bản mới lên hệ thống | UAT: Người dùng thực tế kiểm thử | Go-live: Đưa vào sử dụng chính thức | Batch/bulk: Xử lý hàng loạt | End-to-end: Toàn bộ quy trình từ đầu đến cuối

### 4. VĂN BẢN THUẦN — CẤM KÍ HIỆU AI
Không markdown, không in đậm ** **, không heading #, không ngoặc vuông mẫu, không mũi tên →, không emoji, không thẻ think. Mỗi ý một dòng chữ thường.

## PHẠM VI DỮ LIỆU
- Phạm vi = khoảng ngày đã chọn trên toàn dự án (mọi Sprint và backlog), không giới hạn một Sprint.
- KPI (sprint_progress, week_completed, week_story_points, worklog_hours…) là số liệu nguồn sự thật: giữ nguyên, KHÔNG bịa thêm số.
- KHÔNG bịa tên task, số liệu, người, ngày không có trong dữ liệu.

## CẤU TRÚC JSON (các trường và nội dung yêu cầu)

executive: "ĐIỂM QUẢN LÝ CẦN NẮM" — 2–3 dòng tóm tắt quản trị cho lãnh đạo. Nêu % hoàn thành, tình trạng chung, trọng tâm tiếp theo. KHÔNG dùng thuật ngữ kỹ thuật.

insight: Nhận định ngắn về tiến độ, chất lượng, rủi ro (nếu có). 1–2 câu.

outcomes: Tối đa 8 mục {title, value}. title = tên kết quả nghiệp vụ (KHÔNG tên task kỹ thuật). value = 1 câu: kết quả đạt được, giá trị mang lại, ai làm, xong đến đâu.

sections.result: "KẾT QUẢ THỰC HIỆN". Mỗi dòng: kết quả nghiệp vụ, rồi giá trị mang lại (cách bằng dấu hai chấm). Không liệt kê task kỹ thuật. Cuối phần nêu KPI tổng quan (% hoàn thành, velocity).

sections.current: "TÌNH HÌNH HIỆN TẠI". Nêu rõ Tiến độ, Sản phẩm hiện tại, Chất lượng (ổn định/lỗi), Vướng mắc nếu có. Cực kỳ dễ hiểu, không thuật ngữ IT. Nếu bị block thì viết: "Đang chờ xác nhận/dữ liệu từ bên liên quan" thay vì "Blocked by dependency".

sections.next: "KẾ HOẠCH TIẾP THEO". Chuyển danh sách task thành mốc kết quả mà tổ chức sẽ nhận được. Nêu hạn/ngày khi có trong dữ liệu. KHÔNG copy danh sách task kỹ thuật.

sections.risk: Rủi ro hoặc vướng mắc ảnh hưởng tiến độ. Nếu không có: "Không có hạng mục bị đình trệ hoặc đang chờ xử lý."

sections.feedback: Phản hồi/ghi chú từ người dùng hoặc khách hàng nếu có trong dữ liệu, bằng ngôn ngữ quản lý.

sections.activity: Hoạt động nhóm nổi bật trong kỳ (họp, demo, bàn giao, review…) nếu có.
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
            'contributors' => WeeklyReportTaskFacts::periodContributors($context),
            'blockers' => $context->blockers
                ->take(8)
                ->map(fn ($b) => [
                    'title' => (string) $b->title,
                    'severity' => $b->severity?->value ?? $b->severity,
                    'owner' => $b->relationLoaded('owner') ? $b->owner?->full_name : null,
                ])
                ->values()
                ->all(),
            'feedbacks' => $context->feedbacks
                ->take(8)
                ->map(fn ($f) => [
                    'title' => (string) $f->title,
                    'category' => $f->category?->value ?? $f->category,
                    'rating' => $f->rating,
                ])
                ->values()
                ->all(),
        ];

        return 'Dữ liệu thô kỳ báo cáo (KPI + việc làm + thành viên). '
            .'Hãy ĐỌC rồi TỔNG HỢP thành kết quả nghiệp vụ cho cấp quản lý. '
            .'CẤM liệt kê lại tiêu đề task / API / schema / tên kỹ thuật. '
            ."CẤM copy nguyên văn mô tả task. Gom các việc cùng nghiệp vụ thành 1 kết quả.\n"
            .json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array{executive: string, insight: string, sections: array<string, string>, outcomes: array<int, array{title: string, value: string}>}
     */
    private function parseNarrative(string $raw): array
    {
        $raw = WeeklyReportPlainText::unwrapRaw($raw);

        $data = json_decode($raw, true);
        if (! is_array($data) && preg_match('/\{.*\}/s', $raw, $m)) {
            $data = json_decode($m[0], true);
        }
        if (! is_array($data)) {
            throw new RuntimeException('LLM returned invalid JSON');
        }

        $sections = is_array($data['sections'] ?? null) ? $data['sections'] : [];

        return [
            'executive' => WeeklyReportPlainText::sanitize((string) ($data['executive'] ?? '')),
            'insight' => WeeklyReportPlainText::sanitize((string) ($data['insight'] ?? '')),
            'outcomes' => $this->parseOutcomes($data['outcomes'] ?? []),
            'sections' => [
                'result' => WeeklyReportPlainText::sanitize((string) ($sections['result'] ?? '')),
                'current' => WeeklyReportPlainText::sanitize((string) ($sections['current'] ?? '')),
                'next' => WeeklyReportPlainText::sanitize((string) ($sections['next'] ?? '')),
                'risk' => WeeklyReportPlainText::sanitize((string) ($sections['risk'] ?? '')),
                'feedback' => WeeklyReportPlainText::sanitize((string) ($sections['feedback'] ?? '')),
                'activity' => WeeklyReportPlainText::sanitize((string) ($sections['activity'] ?? '')),
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
            $title = WeeklyReportPlainText::sanitize((string) ($row['title'] ?? ''));
            $value = WeeklyReportPlainText::sanitize((string) ($row['value'] ?? ''));
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
