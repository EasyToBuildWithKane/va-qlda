<?php

namespace App\Support\Realtime;

use App\Models\SystemAccount;

class ThreadSubscribeToken
{
    public static function issue(SystemAccount $account, string $type, int $id): string
    {
        abort_unless(CommentThreadAuthorizer::canSubscribe($account, $type, $id), 403);

        $exp = time() + 3600;
        $payload = "{$account->id}:{$type}:{$id}:{$exp}";
        $sig = hash_hmac('sha256', $payload, (string) config('realtime.secret'));

        return rtrim(strtr(base64_encode("{$payload}:{$sig}"), '+/', '-_'), '=');
    }

    /**
     * @return array{account_id: int, type: string, id: int, exp: int}|null
     */
    public static function verify(string $token): ?array
    {
        $raw = base64_decode(strtr($token, '-_', '+/'), true);
        if ($raw === false || ! str_contains($raw, ':')) {
            return null;
        }

        $parts = explode(':', $raw);
        if (count($parts) !== 5) {
            return null;
        }

        [$accountId, $type, $id, $exp, $sig] = $parts;
        $payload = "{$accountId}:{$type}:{$id}:{$exp}";
        $expected = hash_hmac('sha256', $payload, (string) config('realtime.secret'));

        if (! hash_equals($expected, $sig)) {
            return null;
        }

        if ((int) $exp < time()) {
            return null;
        }

        if (! CommentThreadAuthorizer::isAllowedType($type)) {
            return null;
        }

        return [
            'account_id' => (int) $accountId,
            'type' => $type,
            'id' => (int) $id,
            'exp' => (int) $exp,
        ];
    }

    public static function roomName(string $type, int $id): string
    {
        return "comments:{$type}:{$id}";
    }
}
