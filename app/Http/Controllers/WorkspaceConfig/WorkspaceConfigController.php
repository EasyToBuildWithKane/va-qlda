<?php

namespace App\Http\Controllers\WorkspaceConfig;

use App\Http\Controllers\Controller;
use App\Support\WorkspaceConfig\WorkspaceConfigCatalog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hub for /workspace-config — lists config domains (evaluation, …).
 */
class WorkspaceConfigController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $items = WorkspaceConfigCatalog::forUser($user);

        abort_if($items === [], 403);

        return Inertia::render('WorkspaceConfig/Hub', [
            'items' => $items,
            'summary' => [
                'total' => count($items),
                'live' => count(array_filter($items, static fn (array $i): bool => ($i['status'] ?? '') === 'live')),
            ],
        ]);
    }
}
