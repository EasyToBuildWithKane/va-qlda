<?php

namespace App\Http\Controllers\Credential;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credential\ImportCredentialRequest;
use App\Http\Requests\Credential\StoreCredentialRequest;
use App\Http\Requests\Credential\UpdateCredentialRequest;
use App\Models\Credential;
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

        return back()->with('success', 'Đã cập nhật tài khoản.');
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
        $rows = $request->validated('rows');
        $count = 0;

        DB::transaction(function () use ($rows, $account, $request, &$count) {
            foreach ($rows as $row) {
                $row['created_by'] = $account->id;
                $row['updated_by'] = $account->id;
                $row['status'] ??= CredentialStatus::Active->value;
                $row['environment'] ??= CredentialEnvironment::Production->value;
                $credential = Credential::create($row);
                CredentialActivityLogger::created($credential, $account, $request);
                $count++;
            }
        });

        return back()->with('success', "Đã nhập {$count} tài khoản.");
    }
}
