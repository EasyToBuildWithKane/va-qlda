<?php

namespace App\Http\Controllers\KnowledgeBase;

use App\Http\Controllers\Controller;
use App\Http\Requests\KnowledgeBase\StoreKbArticleRequest;
use App\Http\Requests\KnowledgeBase\UpdateKbArticleRequest;
use App\Http\Resources\KbArticleResource;
use App\Http\Resources\KbCategoryResource;
use App\Models\KbArticle;
use App\Models\KbArticleAttachment;
use App\Models\KbArticleImage;
use App\Models\KbCategory;
use App\Models\KbTag;
use App\Support\Enums\KbArticleStatus;
use App\Support\Enums\KbImageUsage;
use App\Support\KnowledgeBase\KbArticleSearch;
use App\Support\KnowledgeBase\KbContentAnchors;
use App\Support\KnowledgeBase\KbTagSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KbArticleController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', KbArticle::class);

        $account = $request->user();
        $articles = $this->filteredArticlesQuery($request, $account)
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        $favoriteArticles = KbArticle::query()
            ->with('category')
            ->whereIn('id', function ($q) use ($account) {
                $q->select('article_id')
                    ->from('kb_article_favorites')
                    ->where('system_account_id', $account->id);
            })
            ->limit(10)
            ->get(['id', 'title', 'slug']);

        return Inertia::render('KnowledgeBase/Index', [
            'articles' => KbArticleResource::collection($articles),
            'favoriteArticles' => $favoriteArticles,
            'categories' => KbCategoryResource::collection(
                KbCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
            ),
            'tags' => KbTag::query()->orderBy('name')->limit(50)->get(['id', 'name', 'slug']),
            'filters' => (object) $request->only(['q', 'category_id', 'tag', 'status', 'per_page']),
            'options' => [
                'statuses' => KbArticleStatus::options(),
            ],
            'can' => ['create' => $account->can('create', KbArticle::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', KbArticle::class);

        return Inertia::render('KnowledgeBase/Edit', [
            'article' => null,
            'categories' => KbCategoryResource::collection(
                KbCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
            ),
            'options' => ['statuses' => KbArticleStatus::options()],
        ]);
    }

    public function store(StoreKbArticleRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tagNames = $data['tag_names'] ?? null;
        unset($data['tag_names']);

        $account = $request->user();
        abort_if($account->employee_id === null, 403, 'Tài khoản chưa liên kết nhân viên.');

        $data['author_id'] = $account->employee_id;
        $this->applyStatusTimestamps($data);

        $article = DB::transaction(function () use ($data, $tagNames) {
            $article = KbArticle::create($data);
            KbTagSync::sync($article, $tagNames);

            return $article;
        });

        return redirect()
            ->route('knowledge-base.articles.edit', $article)
            ->with('success', 'Đã tạo bài viết. Bạn có thể chèn ảnh và đính kèm file.');
    }

    public function show(Request $request, KbArticle $article): Response
    {
        $this->authorize('view', $article);

        $article->increment('view_count');

        $account = $request->user();
        $article->load(['category', 'author', 'tags', 'attachments', 'galleryImages', 'comments.author']);
        $article->is_favorite = DB::table('kb_article_favorites')
            ->where('system_account_id', $account->id)
            ->where('article_id', $article->id)
            ->exists();
        $article->is_read = DB::table('kb_article_reads')
            ->where('system_account_id', $account->id)
            ->where('article_id', $article->id)
            ->exists();

        $related = KbArticle::query()
            ->published()
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->latest('published_at')
            ->limit(5)
            ->get(['id', 'title', 'slug', 'excerpt', 'published_at']);

        $contentHtml = KbContentAnchors::apply($article->content);
        $toc = KbContentAnchors::toc($article->content);

        $resolved = (new KbArticleResource($article))->resolve();
        $resolved['content'] = $contentHtml;

        return Inertia::render('KnowledgeBase/Show', [
            'article' => $resolved,
            'toc' => $toc,
            'related' => $related,
            'breadcrumb' => [
                ['label' => 'Tri thức', 'href' => route('knowledge-base.index')],
                ['label' => $article->category?->name ?? '—', 'href' => route('knowledge-base.index', ['category_id' => $article->category_id])],
                ['label' => $article->title],
            ],
        ]);
    }

    public function edit(KbArticle $article): Response
    {
        $this->authorize('update', $article);

        $article->load(['tags', 'galleryImages', 'attachments']);

        return Inertia::render('KnowledgeBase/Edit', [
            'article' => (new KbArticleResource($article))->resolve(),
            'categories' => KbCategoryResource::collection(
                KbCategory::query()->where('is_active', true)->orderBy('sort_order')->get(),
            ),
            'options' => ['statuses' => KbArticleStatus::options()],
        ]);
    }

    public function update(UpdateKbArticleRequest $request, KbArticle $article): RedirectResponse
    {
        $data = $request->validated();
        $tagNames = $data['tag_names'] ?? null;
        unset($data['tag_names']);

        if (isset($data['title']) && ! isset($data['slug'])) {
            $data['slug'] = KbArticle::uniqueSlug($data['title'], $article->id);
        }

        $this->applyStatusTimestamps($data, $article);

        DB::transaction(function () use ($article, $data, $tagNames) {
            $article->update($data);
            KbTagSync::sync($article, $tagNames);
        });

        return redirect()
            ->route('knowledge-base.articles.show', $article)
            ->with('success', 'Đã cập nhật bài viết.');
    }

    public function destroy(KbArticle $article): RedirectResponse
    {
        $this->authorize('delete', $article);
        $article->delete();

        return redirect()
            ->route('knowledge-base.index')
            ->with('success', 'Đã xóa bài viết.');
    }

    public function toggleFavorite(Request $request, KbArticle $article): RedirectResponse
    {
        $this->authorize('view', $article);
        $account = $request->user();

        $exists = DB::table('kb_article_favorites')
            ->where('system_account_id', $account->id)
            ->where('article_id', $article->id)
            ->exists();

        if ($exists) {
            DB::table('kb_article_favorites')
                ->where('system_account_id', $account->id)
                ->where('article_id', $article->id)
                ->delete();
        } else {
            DB::table('kb_article_favorites')->insert([
                'system_account_id' => $account->id,
                'article_id' => $article->id,
                'created_at' => now(),
            ]);
        }

        return back();
    }

    public function markRead(Request $request, KbArticle $article): RedirectResponse
    {
        $this->authorize('view', $article);
        $account = $request->user();

        DB::table('kb_article_reads')->updateOrInsert(
            [
                'system_account_id' => $account->id,
                'article_id' => $article->id,
            ],
            [
                'read_at' => now(),
                'created_at' => now(),
            ],
        );

        return back();
    }

    public function storeAttachment(Request $request, KbArticle $article): RedirectResponse
    {
        $this->authorize('update', $article);
        abort_if($request->user()->employee_id === null, 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,zip,txt,png,jpg,jpeg'],
        ]);

        $file = $data['file'];
        $path = $file->store('knowledge-base/'.$article->id.'/attachments', 'public');

        $article->attachments()->create([
            'uploaded_by_id' => $request->user()->employee_id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'Đã tải file lên.');
    }

    public function attachmentFile(KbArticleAttachment $attachment): StreamedResponse
    {
        $attachment->loadMissing('article');
        $this->authorize('view', $attachment->article);

        if (! Storage::disk('public')->exists($attachment->path)) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?? 'application/octet-stream'],
        );
    }

    public function storeImage(Request $request, KbArticle $article): JsonResponse
    {
        $this->authorize('update', $article);
        abort_if($request->user()->employee_id === null, 403);

        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
        ]);

        $file = $data['image'];
        $path = $file->store('knowledge-base/'.$article->id.'/images', 'public');

        $image = $article->images()->create([
            'uploaded_by_id' => $request->user()->employee_id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'usage' => KbImageUsage::Inline->value,
            'created_at' => now(),
        ]);

        return response()->json([
            'url' => route('knowledge-base.images.file', ['image' => $image->id]),
        ]);
    }

    public function imageFile(KbArticleImage $image): StreamedResponse
    {
        $image->loadMissing('article');
        $this->authorize('view', $image->article);

        if (! Storage::disk('public')->exists($image->path)) {
            abort(404);
        }

        return Storage::disk('public')->response(
            $image->path,
            $image->original_name,
            ['Content-Type' => $image->mime_type ?? 'image/jpeg'],
        );
    }

    public function exportData(Request $request): JsonResponse
    {
        $this->authorize('viewAny', KbArticle::class);

        $account = $request->user();
        $rows = $this->filteredArticlesQuery($request, $account)
            ->limit(200)
            ->get();

        return response()->json([
            'articles' => KbArticleResource::collection($rows)->resolve(),
            'filters' => $request->only(['q', 'category_id', 'tag', 'status']),
        ]);
    }

    public function storeGalleryImage(Request $request, KbArticle $article): RedirectResponse
    {
        $this->authorize('update', $article);
        abort_if($request->user()->employee_id === null, 403);

        $data = $request->validate([
            'image' => ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,gif,webp'],
            'alt_text' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $data['image'];
        $path = $file->store('knowledge-base/'.$article->id.'/images', 'public');
        $maxSort = (int) $article->galleryImages()->max('sort_order');

        $article->images()->create([
            'uploaded_by_id' => $request->user()->employee_id,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'alt_text' => $data['alt_text'] ?? null,
            'sort_order' => $maxSort + 1,
            'usage' => KbImageUsage::Gallery->value,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Đã thêm ảnh vào thư viện.');
    }

    public function updateGalleryImage(Request $request, KbArticleImage $image): RedirectResponse
    {
        $image->loadMissing('article');
        $this->authorize('update', $image->article);

        if ($image->usage !== KbImageUsage::Gallery->value) {
            abort(404);
        }

        $data = $request->validate([
            'alt_text' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $image->update($data);

        return back()->with('success', 'Đã cập nhật ảnh.');
    }

    public function destroyGalleryImage(KbArticleImage $image): RedirectResponse
    {
        $image->loadMissing('article');
        $this->authorize('update', $image->article);

        if ($image->usage !== KbImageUsage::Gallery->value) {
            abort(404);
        }

        if ($image->path && Storage::disk('public')->exists($image->path)) {
            Storage::disk('public')->delete($image->path);
        }

        $image->delete();

        return back()->with('success', 'Đã xóa ảnh khỏi thư viện.');
    }

    private function filteredArticlesQuery(Request $request, $account)
    {
        $query = KbArticle::query()
            ->with(['category', 'author', 'tags'])
            ->withCount('comments')
            ->latest('updated_at');

        if (! in_array($account->role->value, ['admin', 'lead'], true)) {
            $query->where('status', KbArticleStatus::Published->value);
        } elseif ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($tag = $request->query('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('slug', $tag));
        }

        if ($search = $request->query('q')) {
            KbArticleSearch::apply($query, $search);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyStatusTimestamps(array &$data, ?KbArticle $existing = null): void
    {
        if (! isset($data['status'])) {
            return;
        }

        $status = KbArticleStatus::from($data['status']);
        if ($status === KbArticleStatus::Published) {
            if (! $existing || $existing->status !== KbArticleStatus::Published) {
                $data['published_at'] = now();
            }
            $data['archived_at'] = null;
        } elseif ($status === KbArticleStatus::Archived) {
            $data['archived_at'] = now();
        } elseif ($status === KbArticleStatus::Draft) {
            $data['published_at'] = null;
            $data['archived_at'] = null;
        }
    }
}
