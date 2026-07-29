<?php

namespace Tests\Unit;

use App\Support\KnowledgeBase\KbContentAnchors;
use PHPUnit\Framework\TestCase;

class KnowledgeBaseSupportTest extends TestCase
{
    public function test_kb_content_anchors_add_ids(): void
    {
        $html = '<h2>Phần 1</h2><p>x</p><h3>Chi tiết</h3>';
        $out = KbContentAnchors::apply($html);

        $this->assertStringContainsString('id="kb-h-0"', $out);
        $this->assertStringContainsString('id="kb-h-1"', $out);

        $toc = KbContentAnchors::toc($html);
        $this->assertCount(2, $toc);
        $this->assertSame('Phần 1', $toc[0]['text']);
    }
}
