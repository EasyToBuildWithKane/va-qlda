<?php

namespace App\Services\Congnghe;

use App\Models\CongngheSoftwareProposal;
use App\Models\CongngheSoftwareProposalAttachment;
use App\Support\Enums\CongngheSoftwareProposalStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CongngheSoftwareProposalRecorder
{
    /**
     * @param  array{name: string, email: string, department: string, title: string, content: string}  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function record(int $systemAccountId, array $data, array $files = []): CongngheSoftwareProposal
    {
        return DB::transaction(function () use ($systemAccountId, $data, $files): CongngheSoftwareProposal {
            $proposal = CongngheSoftwareProposal::query()->create([
                'system_account_id' => $systemAccountId,
                'submitter_name' => $data['name'],
                'submitter_email' => $data['email'],
                'department' => $data['department'],
                'title' => $data['title'],
                'content' => $data['content'],
                'status' => CongngheSoftwareProposalStatus::New,
            ]);

            $proposal->assignReferenceCode();

            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $mime = $file->getMimeType() ?? '';
                $isImage = str_starts_with($mime, 'image/')
                    || in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);

                $path = $file->store("congnghe/proposals/{$proposal->id}", 'public');

                CongngheSoftwareProposalAttachment::query()->create([
                    'congnghe_software_proposal_id' => $proposal->id,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $mime !== '' ? $mime : null,
                    'size' => (int) $file->getSize(),
                    'is_image' => $isImage,
                ]);
            }

            return $proposal->load('attachments');
        });
    }
}
