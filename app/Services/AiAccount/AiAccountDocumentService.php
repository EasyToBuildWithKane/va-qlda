<?php

namespace App\Services\AiAccount;

use App\Models\AiAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiAccountDocumentService
{
    public const KIND_PROPOSAL = 'proposal';

    public const KIND_PAYMENT_REQUEST = 'payment_request';

    /**
     * @param  array<int, UploadedFile>|null  $files
     * @return list<array{path:string,original_name:string,mime_type:?string,size:int}>
     */
    public function storeUploads(AiAccount $account, string $kind, ?array $files): array
    {
        if ($files === null || $files === []) {
            return [];
        }

        $folder = $kind === self::KIND_PAYMENT_REQUEST ? 'payment-request' : 'proposal';
        $stored = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
            $safeName = $safeName !== '' ? $safeName : 'file';
            $ext = $file->getClientOriginalExtension();
            $filename = $safeName.'-'.Str::random(8).($ext !== '' ? '.'.$ext : '');
            $path = $file->storeAs("ai-accounts/{$account->id}/{$folder}", $filename, 'public');

            if (! is_string($path) || $path === '') {
                continue;
            }

            $stored[] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
            ];
        }

        return $stored;
    }

    /**
     * @param  list<array{path:string,original_name?:string,mime_type?:?string,size?:int}>  $docs
     */
    public function deleteFiles(array $docs): void
    {
        foreach ($docs as $doc) {
            $path = $doc['path'] ?? null;
            if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
