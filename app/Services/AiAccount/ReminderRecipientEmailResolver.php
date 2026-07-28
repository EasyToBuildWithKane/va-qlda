<?php

namespace App\Services\AiAccount;

use App\Models\Hrm\HrmUser;
use App\Models\SystemAccount;
use App\Services\Hrm\HrmIdentityResolver;

/**
 * Địa chỉ nhận mail nhắc AI: ưu tiên email HRM (va_hrm SSOT) đã liên kết vào employees.email.
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

        if ($employee?->hrm_user_id !== null && $this->hrmResolver->isHrmConfigured()) {
            $hrmEmail = HrmUser::query()
                ->withTrashed()
                ->whereKey($employee->hrm_user_id)
                ->value('email');

            if ($this->isValidEmail($hrmEmail)) {
                return strtolower(trim($hrmEmail));
            }
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
