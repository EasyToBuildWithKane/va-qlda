<?php

namespace App\Http\Controllers\Congnghe;

use App\Http\Controllers\Controller;
use App\Models\CongngheSoftwareProposal;
use App\Models\CongngheSoftwareProposalAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CongngheSoftwareProposalAttachmentController extends Controller
{
    public function file(
        CongngheSoftwareProposal $proposal,
        CongngheSoftwareProposalAttachment $attachment,
    ): StreamedResponse {
        $this->authorize('view', $proposal);

        if ($attachment->congnghe_software_proposal_id !== $proposal->id) {
            abort(404);
        }

        if (! Storage::disk('public')->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream'],
        );
    }
}
