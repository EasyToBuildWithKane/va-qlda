<?php

namespace Tests\Unit;

use App\Support\Profile\SkillCatalog;
use Tests\TestCase;

class SkillCatalogTest extends TestCase
{
    public function test_build_preserves_custom_skill_title_verbatim(): void
    {
        $built = SkillCatalog::build(
            ['Lập trình Laravel'],
            [
                [
                    'name' => 'Thiết kế API nội bộ',
                    'level' => 4,
                    'category' => 'Lập trình Web',
                ],
            ],
        );

        $this->assertSame(2, $built['total']);
        $titles = collect($built['groups'])->flatMap(fn ($g) => collect($g['items'])->pluck('name'))->all();
        $this->assertContains('Thiết kế API nội bộ', $titles);
        $this->assertContains('Lập trình Laravel', $titles);
    }

    public function test_build_reads_title_alias_in_skill_details(): void
    {
        $built = SkillCatalog::build([], [
            ['title' => 'Quản lý sprint', 'level' => 3, 'category' => 'Quản lý'],
        ]);

        $this->assertSame(1, $built['total']);
        $this->assertSame('Quản lý sprint', $built['groups'][0]['items'][0]['name']);
    }
}
