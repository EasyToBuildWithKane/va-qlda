<?php

namespace App\Http\Controllers\Credential;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credential\ImportCredentialRequest;
use App\Http\Requests\Credential\StoreCredentialRequest;
use App\Http\Requests\Credential\UpdateCredentialRequest;
use App\Models\Credential;
use App\Models\CredentialImportLog;
use App\Models\CredentialPasswordHistory;
use App\Support\Credential\CredentialActivityLogger;
use App\Support\Enums\CredentialEnvironment;
use App\Support\Enums\CredentialStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CredentialController extends Controller
{
    public function store(StoreCredentialRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['status'] ??= CredentialStatus::Active->value;
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;

        if (! empty($data['login_password'])) {
            $data['password_changed_at'] = now();
        }

        $credential = DB::transaction(function () use ($data, $request) {
            $credential = Credential::create($data);
            CredentialActivityLogger::created($credential, $request->user(), $request);

            return $credential;
        });

        return redirect()
            ->route('credentials.show', $credential)
            ->with('success', 'Đã tạo tài khoản.');
    }

    public function update(UpdateCredentialRequest $request, Credential $credential): RedirectResponse
    {
        $this->authorize('update', $credential);

        $data = $request->validated();
        $data['updated_by'] = $request->user()->id;

        if (array_key_exists('login_password', $data) && $data['login_password'] !== null && $data['login_password'] !== '') {
            if ($credential->login_password) {
                CredentialPasswordHistory::create([
                    'credential_id' => $credential->id,
                    'encrypted_password' => $credential->login_password,
                    'changed_by' => $request->user()->id,
                    'changed_at' => now(),
                    'notes' => 'Lưu trước khi đổi mật khẩu',
                ]);
            }
            $data['password_changed_at'] = now();
            CredentialActivityLogger::changedPassword($credential, $request->user(), $request);
        } elseif (array_key_exists('login_password', $data) && ($data['login_password'] === null || $data['login_password'] === '')) {
            unset($data['login_password']);
        }

        $changes = collect($data)->keys()->all();
        $credential->update($data);
        CredentialActivityLogger::updated($credential, $request->user(), $changes, $request);

        return redirect()
            ->route('credentials.show', $credential)
            ->with('success', 'Đã cập nhật tài khoản.');
    }

    public function destroy(Request $request, Credential $credential): RedirectResponse
    {
        $this->authorize('delete', $credential);

        DB::transaction(function () use ($credential, $request) {
            CredentialActivityLogger::deleted($credential, $request->user(), $request);
            $credential->delete();
        });

        return redirect()
            ->route('credentials.index')
            ->with('success', 'Đã xóa tài khoản.');
    }

    public function showPassword(Request $request, Credential $credential): JsonResponse
    {
        $this->authorize('viewPassword', $credential);

        $action = $request->query('action', 'view');
        if ($action === 'copy') {
            CredentialActivityLogger::copiedPassword($credential, $request->user(), $request);
        } else {
            CredentialActivityLogger::viewedPassword($credential, $request->user(), $request);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'password' => $credential->login_password,
            ],
        ]);
    }

    public function import(ImportCredentialRequest $request): RedirectResponse
    {
        $account = $request->user();
        $validated = $request->validated();
        $rows = $validated['rows'];
        $overwrite = (bool) ($validated['overwrite'] ?? false);
        $count = 0;
        $overwrittenCount = 0;

        DB::transaction(function () use ($rows, $account, $request, $overwrite, &$count, &$overwrittenCount) {
            foreach ($rows as $row) {
                $row['status'] ??= CredentialStatus::Active->value;
                $row['environment'] ??= CredentialEnvironment::Production->value;

                if ($overwrite) {
                    $existing = Credential::where('name', $row['name'])
                        ->where('system_category', $row['system_category'])
                        ->first();
                    if ($existing) {
                        $row['updated_by'] = $account->id;
                        $existing->update($row);
                        CredentialActivityLogger::updated($existing, $account, array_keys($row), $request);
                        $overwrittenCount++;

                        continue;
                    }
                }

                $row['created_by'] = $account->id;
                $row['updated_by'] = $account->id;
                $credential = Credential::create($row);
                CredentialActivityLogger::created($credential, $account, $request);
                $count++;
            }

            CredentialImportLog::create([
                'account_id' => $account->id,
                'imported_count' => $count,
                'overwritten_count' => $overwrittenCount,
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        });

        $message = "Đã nhập {$count} tài khoản";
        if ($overwrittenCount > 0) {
            $message .= ", ghi đè {$overwrittenCount} bản ghi";
        }

        return back()->with('success', "{$message}.");
    }

    public function exportData(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Credential::class);

        $account = $request->user();
        $query = Credential::query()
            ->visibleTo($account)
            ->with(['owner', 'project', 'department'])
            ->latest();

        // Apply same filters as index page
        if ($q = $request->query('q')) {
            $query->where(function ($qb) use ($q) {
                $qb->where('name', 'like', "%{$q}%")
                    ->orWhere('username', 'like', "%{$q}%")
                    ->orWhere('login_url', 'like', "%{$q}%")
                    ->orWhere('provider_name', 'like', "%{$q}%");
            });
        }
        foreach (['status', 'credential_type', 'system_category', 'environment'] as $field) {
            if ($val = $request->query($field)) {
                $query->where($field, $val);
            }
        }
        if ($pid = $request->query('project_id')) {
            $query->where('project_id', $pid);
        }
        if ($did = $request->query('department_id')) {
            $query->where('department_id', $did);
        }
        if ($oid = $request->query('owner_id')) {
            $query->where('owner_id', $oid);
        }

        $credentials = $query->limit(2000)->get()->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'credential_type' => $c->credential_type,
            'system_category' => $c->system_category,
            'username' => $c->username,
            'login_url' => $c->login_url,
            'provider_name' => $c->provider_name,
            'environment' => $c->environment,
            'status' => $c->status,
            'owner' => $c->owner?->display_name,
            'expires_at' => $c->expires_at?->toDateString(),
        ]);

        return response()->json(['data' => $credentials]);
    }

    public function importLogs(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Credential::class);

        $logs = CredentialImportLog::with('account:id,display_name')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'user' => $log->account?->display_name ?? 'Không xác định',
                'imported_count' => $log->imported_count,
                'overwritten_count' => $log->overwritten_count,
                'created_at' => $log->created_at?->toDateTimeString(),
                'created_at_human' => $log->created_at?->diffForHumans(),
            ]);

        return response()->json(['data' => $logs]);
    }
}
