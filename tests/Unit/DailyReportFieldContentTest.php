<?php

namespace Tests\Unit;

use App\Support\DailyReportFieldContent;
use Tests\TestCase;

class DailyReportFieldContentTest extends TestCase
{
    public function test_strips_script_tags_and_their_content(): void
    {
        $clean = DailyReportFieldContent::sanitize('<p>Hi</p><script>alert(1)</script>');

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('alert(1)', $clean);
        $this->assertStringContainsString('<p>Hi</p>', $clean);
    }

    public function test_strips_event_handler_attributes(): void
    {
        $clean = DailyReportFieldContent::sanitize('<p onclick="steal()">x</p><img src=x onerror="alert(1)">');

        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringNotContainsString('onerror', $clean);
        // <img> is not allowlisted, so it is unwrapped entirely.
        $this->assertStringNotContainsString('<img', $clean);
    }

    public function test_removes_javascript_and_data_uri_hrefs_but_keeps_http_links(): void
    {
        $danger = DailyReportFieldContent::sanitize('<a href="javascript:alert(1)">x</a>');
        $this->assertStringNotContainsString('javascript:', $danger);

        $safe = DailyReportFieldContent::sanitize('<a href="https://vaschools.edu.vn">x</a>');
        $this->assertStringContainsString('href="https://vaschools.edu.vn"', $safe);
        $this->assertStringContainsString('rel="noopener nofollow ugc"', $safe);
    }

    public function test_preserves_allowed_formatting_and_unicode(): void
    {
        $html = '<p><strong>Hoàn thành</strong> API</p><ul><li>Việc A</li></ul>';

        $this->assertSame($html, DailyReportFieldContent::sanitize($html));
    }

    public function test_unwraps_disallowed_tags_but_keeps_text(): void
    {
        $clean = DailyReportFieldContent::sanitize('<div><span>kept text</span></div>');

        $this->assertStringNotContainsString('<div', $clean);
        $this->assertStringNotContainsString('<span', $clean);
        $this->assertStringContainsString('kept text', $clean);
    }

    public function test_passes_through_null_and_empty(): void
    {
        $this->assertNull(DailyReportFieldContent::sanitize(null));
        $this->assertSame('', DailyReportFieldContent::sanitize('   '));
    }

    public function test_sanitize_payload_only_touches_content_keys(): void
    {
        $payload = DailyReportFieldContent::sanitizePayload([
            'goals_today' => '<p>ok</p><script>bad()</script>',
            'title' => '<b>not a content key</b>',
            'project_id' => 5,
        ]);

        $this->assertStringNotContainsString('script', $payload['goals_today']);
        $this->assertSame('<b>not a content key</b>', $payload['title']);
        $this->assertSame(5, $payload['project_id']);
    }
}
