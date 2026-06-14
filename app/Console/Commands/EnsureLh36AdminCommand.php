<?php

namespace App\Console\Commands;

use Database\Seeders\HiddenAdminLoginSeeder;
use Illuminate\Console\Command;

class EnsureLh36AdminCommand extends Command
{
    protected $signature = 'va:ensure-lh36-admin';

    protected $description = 'Tạo/cập nhật tài khoản đăng nhập ẩn /lh36 (usr_01, role admin)';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => HiddenAdminLoginSeeder::class, '--force' => true]);

        $this->info('Đăng nhập: /lh36 — usr_01 / password01');

        return self::SUCCESS;
    }
}
