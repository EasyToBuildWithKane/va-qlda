<?php

namespace Tests\Feature;

use App\Support\Auth\TechLoginAccess;
use Tests\TestCase;

class TechLoginAccessTest extends TestCase
{
    public function test_default_whitelist_includes_phong_cong_nghe_email(): void
    {
        $this->assertTrue(TechLoginAccess::isAllowedEmail('phongcongnghe@vaschools.edu.vn'));
    }

    public function test_whitelist_is_case_insensitive(): void
    {
        $this->assertTrue(TechLoginAccess::isAllowedEmail('ToanBQ@vaschools.edu.vn'));
    }

    public function test_random_org_email_not_on_whitelist(): void
    {
        $this->assertFalse(TechLoginAccess::isAllowedEmail('random@vaschools.edu.vn'));
    }
}
