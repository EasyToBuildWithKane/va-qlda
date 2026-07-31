<?php

namespace App\Support\WorkspaceConfig;

use App\Models\SystemAccount;
use App\Models\WorkspaceConfig\WorkspaceProfile;
use App\Support\Enums\WorkspaceProfileStatus;
use App\Support\Evaluation\HrmDepartmentDirectory;
use InvalidArgumentException;

/**
 * Lazy-create / reactivate a workspace profile from the HRM department directory.
 */
final class WorkspaceProfileProvisioner
{
    public function __construct(
        private readonly HrmDepartmentDirectory $departments,
    ) {}

    public function ensure(string $departmentCode, SystemAccount $actor, bool $activate = true): WorkspaceProfile
    {
        $departmentCode = trim($departmentCode);
        if ($departmentCode === '') {
            throw new InvalidArgumentException('Thiếu mã phòng ban.');
        }

        $dept = $this->departments->findByCode($departmentCode);
        if ($dept === null) {
            throw new InvalidArgumentException('Không tìm thấy phòng ban trong danh mục.');
        }

        /** @var WorkspaceProfile $profile */
        $profile = WorkspaceProfile::withTrashed()
            ->where('department_code', $dept['code'])
            ->first();

        if ($profile === null) {
            return WorkspaceProfile::query()->create([
                'department_code' => $dept['code'],
                'department_name' => $dept['name'],
                'local_department_id' => $dept['local_department_id'],
                'status' => $activate ? WorkspaceProfileStatus::Active : WorkspaceProfileStatus::Draft,
                'created_by' => $actor->id,
            ]);
        }

        if ($profile->trashed()) {
            $profile->restore();
        }

        $profile->fill([
            'department_name' => $dept['name'],
            'local_department_id' => $dept['local_department_id'] ?? $profile->local_department_id,
        ]);

        if ($activate && $profile->status !== WorkspaceProfileStatus::Active) {
            $profile->status = WorkspaceProfileStatus::Active;
        }

        $profile->save();

        return $profile->fresh();
    }

    /**
     * @param  list<string>  $codes
     * @return array{created: int, activated: int, skipped: int, codes: list<string>}
     */
    public function ensureMany(array $codes, SystemAccount $actor, bool $activate = true): array
    {
        $created = 0;
        $activated = 0;
        $skipped = 0;
        $okCodes = [];

        foreach ($codes as $code) {
            $trim = trim((string) $code);
            if ($trim === '') {
                $skipped++;

                continue;
            }

            if ($this->departments->findByCode($trim) === null) {
                $skipped++;

                continue;
            }

            $existing = WorkspaceProfile::withTrashed()
                ->where('department_code', $trim)
                ->first();

            $wasMissing = $existing === null || $existing->trashed();
            $wasInactive = $existing !== null
                && ! $existing->trashed()
                && $existing->status !== WorkspaceProfileStatus::Active;

            try {
                $profile = $this->ensure($trim, $actor, $activate);
            } catch (InvalidArgumentException) {
                $skipped++;

                continue;
            }

            if ($wasMissing) {
                $created++;
            } elseif ($wasInactive && $profile->status === WorkspaceProfileStatus::Active) {
                $activated++;
            } else {
                $activated++;
            }

            $okCodes[] = $profile->department_code;
        }

        return [
            'created' => $created,
            'activated' => $activated,
            'skipped' => $skipped,
            'codes' => $okCodes,
        ];
    }
}
