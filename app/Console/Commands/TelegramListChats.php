<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Liệt kê chat_id từ getUpdates sau khi bot nhận tin trong group/channel.
 *
 * Cách dùng: thêm bot vào group → tắt Group Privacy (BotFather) → gửi một tin → chạy lệnh này.
 */
class TelegramListChats extends Command
{
    protected $signature = 'telegram:list-chats';

    protected $description = 'Liệt kê chat_id Telegram từ getUpdates (sau khi gửi tin trong group/channel)';

    public function handle(): int
    {
        if (! config('telegram.enabled') || ! filled(config('telegram.bot_token'))) {
            $this->error('Bật TELEGRAM_ENABLED và TELEGRAM_BOT_TOKEN trong .env.');

            return self::FAILURE;
        }

        $token = (string) config('telegram.bot_token');
        $response = Http::timeout(15)->get("https://api.telegram.org/bot{$token}/getUpdates", [
            'limit' => 100,
        ]);

        if (! $response->successful() || ! ($response->json('ok') ?? false)) {
            $this->error('Gọi Telegram thất bại: '.($response->json('description') ?? $response->body()));

            return self::FAILURE;
        }

        $updates = $response->json('result') ?? [];
        if ($updates === []) {
            $this->warn('Không có update nào trong hàng đợi.');
            $this->line('1. Thêm bot vào group/channel (channel: cấp quyền admin + đăng bài).');
            $this->line('2. Group: BotFather → Group Privacy → Turn off.');
            $this->line('3. Gửi một tin trong group/channel, chạy lại lệnh này.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($updates as $update) {
            foreach (['message', 'channel_post', 'my_chat_member', 'chat_member'] as $key) {
                $payload = $update[$key] ?? null;
                if (! is_array($payload)) {
                    continue;
                }
                $chat = $payload['chat'] ?? $payload['from'] ?? null;
                if (! is_array($chat) || ! isset($chat['id'])) {
                    if (isset($payload['chat'])) {
                        $chat = $payload['chat'];
                    } else {
                        continue;
                    }
                }
                if (! is_array($chat) || ! isset($chat['id'])) {
                    continue;
                }
                $id = (string) $chat['id'];
                $rows[$id] = [
                    'chat_id' => $id,
                    'type' => $chat['type'] ?? '—',
                    'title' => $chat['title'] ?? ($chat['username'] ?? ($chat['first_name'] ?? '—')),
                ];
            }
        }

        if ($rows === []) {
            $this->warn('Có update nhưng không parse được chat — thử gửi tin text trong group.');

            return self::SUCCESS;
        }

        $this->info('Gán chat_id vào .env (VD: TELEGRAM_BLOCKER_CHAT_ID):');
        $this->table(['chat_id', 'type', 'title'], array_values($rows));

        return self::SUCCESS;
    }
}
