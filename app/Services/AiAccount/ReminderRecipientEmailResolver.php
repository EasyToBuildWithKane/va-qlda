<?php

namespace App\Services\AiAccount;

use App\Models\SystemAccount;
use App\Services\Hrm\HrmIdentityResolver;

/**
 * Địa chỉ nhận mail nhắc AI: ưu tiên email nhân sự Workspace (đã refresh từ HRM API nếu cấu hình).
 */
final class ReminderRecipientEmailResolver
{
    public function __construct(
        private readonly HrmIdentityResolver $hrmResolver,
    ) {}

    public function resolve(SystemAccount $account): ?string
    {
        $employee = $account->employee;
        if ($employee !== null && $this->hrmResolver->isHrmConfigured()) {
            $employee = $this->hrmResolver->refreshEmployeeIfLinked($employee);
        }

        $email = $employee?->email;
        if ($this->isValidEmail($email)) {
            return strtolower(trim($email));
        }

        $username = $account->username;
        if ($this->isValidEmail($username)) {
            return strtolower(trim($username));
        }

        return null;
    }

    private function isValidEmail(mixed $value): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL);
    }
}
