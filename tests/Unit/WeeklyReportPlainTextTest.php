<?php

namespace Tests\Unit;

use App\Support\WeeklyReport\WeeklyReportPlainText;
use Tests\TestCase;

class WeeklyReportPlainTextTest extends TestCase
{
    public function test_sanitize_strips_markdown_arrows_and_list_markers(): void
    {
        $raw = <<<'TEXT'
### Kết quả
- **Hoàn thiện quy trình rút học bạ** → giúp theo dõi từ yêu cầu đến bàn giao.
* [Quản lý chuyển lớp] -> theo dõi học sinh đổi lớp.
1. `API` đã xong
📌 Tiến độ **80%**
TEXT;

        $clean = WeeklyReportPlainText::sanitize($raw);

        $this->assertStringNotContainsString('**', $clean);
        $this->assertStringNotContainsString('###', $clean);
        $this->assertStringNotContainsString('`', $clean);
        $this->assertStringNotContainsString('→', $clean);
        $this->assertStringNotContainsString('[', $clean);
        $this->assertStringNotContainsString('📌', $clean);
        $this->assertStringContainsString('Hoàn thiện quy trình rút học bạ: giúp theo dõi từ yêu cầu đến bàn giao.', $clean);
        $this->assertStringContainsString('Quản lý chuyển lớp: theo dõi học sinh đổi lớp.', $clean);
        $this->assertStringContainsString('API đã xong', $clean);
        $this->assertStringContainsString('Tiến độ 80%', $clean);
    }

    public function test_unwrap_raw_strips_thinking_and_json_fence(): void
    {
        $raw = <<<'TEXT'
<think>Tôi sẽ viết JSON</think>
```json
{"executive":"Tóm tắt"}
```
TEXT;

        $this->assertSame('{"executive":"Tóm tắt"}', WeeklyReportPlainText::unwrapRaw($raw));
    }

    public function test_sanitize_is_noop_for_plain_vietnamese(): void
    {
        $text = "Hoàn thiện quy trình rút học bạ điện tử.\nĐang đúng tiến độ 80%.";

        $this->assertSame($text, WeeklyReportPlainText::sanitize($text));
    }
}
