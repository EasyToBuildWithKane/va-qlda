<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contract\StoreContractAttachmentRequest;
use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Support\ContractActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContractAttachmentController extends Controller
{
    public function file(Contract $contract, ContractAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $contract);

        if ($attachment->contract_id !== $contract->id || $attachment->isExternalLink()) {
            abort(404);
        }

        if (! filled($attachment->path) || ! Storage::disk('public')->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream'],
        );
    }

    public function store(StoreContractAttachmentRequest $request, Contract $contract): RedirectResponse
    {
        $account = $request->user();
        $data = $request->validated();
        $category = $data['category'];

        $externalUrl = trim((string) ($data['external_url'] ?? ''));
        if ($externalUrl !== '') {
            $title = trim((string) ($data['title'] ?? ''));
            $name = $title !== '' ? $title : $externalUrl;

            $attachment = $contract->attachments()->create([
                'category' => $category,
                'uploaded_by_id' => $account->employee_id,
                'original_name' => $name,
                'notes' => $data['notes'] ?? null,
                'path' => null,
                'external_url' => $externalUrl,
                'mime_type' => null,
                'size' => 0,
                'is_image' => false,
                'version' => $this->nextVersion($contract, $category, $name),
            ]);

            ContractActivityLogger::attachmentAdded($contract, $attachment->original_name, $account);

            return back()->with('success', 'Đã thêm link hồ sơ.');
        }

        foreach ($request->file('files', []) as $file) {
            $path = $file->store("contracts/{$contract->id}/{$category}", 'public');
            $mime = $file->getMimeType() ?? '';
            $name = $file->getClientOriginalName();

            $attachment = $contract->attachments()->create([
                'category' => $category,
                'uploaded_by_id' => $account->employee_id,
                'original_name' => $name,
                'notes' => $data['notes'] ?? null,
                'path' => $path,
                'mime_type' => $mime,
                'size' => $file->getSize(),
                'is_image' => str_starts_with($mime, 'image/'),
                'version' => $this->nextVersion($contract, $category, $name),
            ]);

            ContractActivityLogger::attachmentAdded($contract, $attachment->original_name, $account);
        }

        return back()->with('success', 'Đã tải lên hồ sơ.');
    }

    public function destroy(Request $request, Contract $contract, ContractAttachment $attachment): RedirectResponse
    {
        $this->authorize('update', $contract);

        if ($attachment->contract_id !== $contract->id) {
            abort(404);
        }

        ContractActivityLogger::attachmentRemoved($contract, $attachment->original_name, $request->user());

        if (! $attachment->isExternalLink() && filled($attachment->path)) {
            Storage::disk('public')->delete($attachment->path);
        }

        $attachment->delete();

        return back()->with('success', 'Đã xoá hồ sơ.');
    }

    /** Version kế tiếp cho cùng (hợp đồng, loại, tên) — giữ lịch sử phiên bản. */
    private function nextVersion(Contract $contract, string $category, string $name): int
    {
        return (int) $contract->attachments()
            ->where('category', $category)
            ->where('original_name', $name)
            ->max('version') + 1;
    }
}
