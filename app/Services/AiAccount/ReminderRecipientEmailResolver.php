<?php

namespace App\Services\AiAccount;

use App\Models\Cms\CmsUser;
use App\Models\SystemAccount;
use App\Services\Cms\CmsEmployeeSyncService;

/**
 * Địa chỉ nhận mail nhắc AI: ưu tiên email CMS đã sync vào employees.email.
 */
final class ReminderRecipientEmailResolver
{
    public function __construct(
        private readonly CmsEmployeeSyncService $cmsSync,
    ) {}

    public function resolve(SystemAccount $account): ?string
    {
        $employee = $account->employee;
        if ($employee !== null && $this->cmsSync->isCmsConfigured()) {
            $employee = $this->cmsSync->refreshEmployeeIfLinked($employee);
        }

        $email = $employee?->email;
        if ($this->isValidEmail($email)) {
            return strtolower(trim($email));
        }

        if ($employee?->cms_user_id !== null && $this->cmsSync->isCmsConfigured()) {
            $cmsEmail = CmsUser::query()
                ->withTrashed()
                ->whereKey($employee->cms_user_id)
                ->value('email');

            if ($this->isValidEmail($cmsEmail)) {
                return strtolower(trim($cmsEmail));
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
