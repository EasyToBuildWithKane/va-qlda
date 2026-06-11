<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class RealtimePingCommand extends Command
{
    protected $signature = 'realtime:ping {--channel= : Redis channel (default from config)}';

    protected $description = 'Publish một message test lên kênh realtime (dùng với redis-cli SUBSCRIBE trên server)';

    public function handle(): int
    {
        if (! config('realtime.enabled')) {
            $this->warn('REALTIME_ENABLED=false — bật trong .env rồi chạy lại.');

            return self::FAILURE;
        }

        $channel = $this->option('channel') ?: (string) config('realtime.redis_channel');
        $payload = json_encode([
            'event' => 'ping',
            'room' => 'comments:task:0',
            'data' => ['at' => now()->toIso8601String()],
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            $this->error('Không encode được payload.');

            return self::FAILURE;
        }

        try {
            $receivers = (int) Redis::publish($channel, $payload);
        } catch (\Throwable $e) {
            $this->error('Redis publish failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Đã publish lên [{$channel}] — {$receivers} subscriber(s) nhận.");
        $this->line($payload);
        if ($receivers === 0) {
            $this->warn('0 subscriber: chạy `redis-cli SUBSCRIBE '.$channel.'` hoặc `npm run realtime` / systemd Node trước khi ping.');
        }

        return self::SUCCESS;
    }
}
