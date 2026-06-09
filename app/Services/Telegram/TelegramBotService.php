<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    public function isConfigured(): bool
    {
        return (bool) config('telegram.enabled')
            && filled(config('telegram.bot_token'))
            && filled(config('telegram.chat_id'));
    }

    public function sendMessage(string $text, ?string $parseMode = 'HTML'): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $token = (string) config('telegram.bot_token');
        $url = "https://api.telegram.org/bot{$token}/sendMessage";

        try {
            $response = Http::timeout(10)->asForm()->post($url, array_filter([
                'chat_id' => config('telegram.chat_id'),
                'text' => $text,
                'parse_mode' => $parseMode,
                'disable_web_page_preview' => true,
            ]));

            if (! $response->successful() || ! ($response->json('ok') ?? false)) {
                Log::warning('Telegram sendMessage failed', [
                    'status' => $response->status(),
                    'description' => $response->json('description'),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Telegram sendMessage exception', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
