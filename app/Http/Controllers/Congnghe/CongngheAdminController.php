<?php

namespace App\Http\Controllers\Congnghe;

use App\Http\Controllers\Controller;
use App\Http\Requests\Congnghe\ReorderCongngheSectionsRequest;
use App\Http\Requests\Congnghe\UpdateCongngheSectionRequest;
use App\Models\CongngheSection;
use App\Support\Congnghe\CongngheContentRepository;
use App\Support\Congnghe\CongngheContentSchema;
use App\Support\Congnghe\CongngheIcons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Quản trị nội dung trang /congnghe (admin-only). Controller mỏng: đọc/ghi qua
 * CongngheContentRepository; schema sinh cả payload editor lẫn rule validation.
 */
class CongngheAdminController extends Controller
{
    public function __construct(private readonly CongngheContentRepository $content) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CongngheSection::class);

        return Inertia::render('CongngheAdmin/Index', [
            'sections' => $this->content->forAdmin(),
            'icons' => CongngheIcons::catalog(),
            'metricKeys' => collect(CongngheContentSchema::METRIC_KEYS)
                ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
                ->values()
                ->all(),
            'tones' => CongngheContentSchema::TONES,
            'can' => ['manage' => $request->user()->can('manage', CongngheSection::class)],
        ]);
    }

    public function update(UpdateCongngheSectionRequest $request, string $section): RedirectResponse
    {
        $this->authorize('manage', CongngheSection::class);
        abort_unless(CongngheContentSchema::exists($section), 404);

        $validated = $request->validated();
        $content = is_array($validated['content'] ?? null) ? $validated['content'] : [];
        $visible = $request->has('is_visible') ? $request->boolean('is_visible') : true;

        DB::transaction(function () use ($section, $content, $visible, $request) {
            $this->content->save($section, $content, $visible, $request->user()->id);
        });

        return back()->with('success', 'Đã lưu nội dung mục «'.CongngheContentSchema::label($section).'».');
    }

    public function reset(Request $request, string $section): RedirectResponse
    {
        $this->authorize('manage', CongngheSection::class);
        abort_unless(CongngheContentSchema::exists($section), 404);

        $this->content->reset($section);

        return back()->with('success', 'Đã khôi phục nội dung mặc định.');
    }

    public function reorder(ReorderCongngheSectionsRequest $request): RedirectResponse
    {
        $this->authorize('manage', CongngheSection::class);

        DB::transaction(function () use ($request) {
            $this->content->reorder($request->validated()['order'], $request->user()->id);
        });

        return back()->with('success', 'Đã cập nhật thứ tự các mục.');
    }
}
