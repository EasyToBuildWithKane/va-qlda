<?php

namespace Tests\Unit;

use App\Support\EvidenceLinkPreview;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EvidenceLinkPreviewTest extends TestCase
{
    public function test_resolves_direct_https_image_url(): void
    {
        $url = 'https://cdn.example.com/shots/demo.png';

        $this->assertSame($url, EvidenceLinkPreview::resolveImageUrl($url));
    }

    public function test_rejects_non_https_url(): void
    {
        $this->assertNull(EvidenceLinkPreview::resolveImageUrl('http://prnt.sc/abc'));
    }

    public function test_resolves_prnt_sc_via_og_image(): void
    {
        Http::fake([
            'https://prnt.sc/abc-code' => Http::response(
                '<html><head><meta property="og:image" content="https://img.lightshot.app/demo.png"/></head></html>',
                200,
            ),
        ]);

        $this->assertSame(
            'https://img.lightshot.app/demo.png',
            EvidenceLinkPreview::resolveImageUrl('https://prnt.sc/abc-code'),
        );
    }

    public function test_returns_null_when_fetch_fails(): void
    {
        Http::fake([
            'https://prnt.sc/missing' => Http::response('', 404),
        ]);

        $this->assertNull(EvidenceLinkPreview::resolveImageUrl('https://prnt.sc/missing'));
    }
}
