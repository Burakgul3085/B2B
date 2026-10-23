<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Content\Blog;
use App\Domain\Store\Storefront\ProductSkus;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBlogCategory;
use App\Models\Store\StoreBlogPost;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Vitrin → Blog: yazılar ve kategoriler. */
class BlogController extends Controller
{
    private const FIELDS = ['title', 'slug', 'category_id', 'excerpt', 'body', 'published_at', 'seo_title', 'seo_description', 'product_skus'];

    public function __construct(private Blog $blog) {}

    public function index(): View
    {
        return view('store.admin.blog.index', [
            'posts' => StoreBlogPost::with('category:id,name')->orderByDesc('published_at')->orderByDesc('id')->paginate(30),
            'categories' => StoreBlogCategory::withCount('posts')->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('store.admin.blog.form', ['post' => new StoreBlogPost, 'skus' => '', 'categories' => $this->categoryOptions()]);
    }

    public function edit(StoreBlogPost $storeBlogPost): View
    {
        return view('store.admin.blog.form', [
            'post' => $storeBlogPost,
            'skus' => ProductSkus::of($storeBlogPost->products()->pluck('store_products.id')->all()),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, null);
    }

    public function update(Request $request, StoreBlogPost $storeBlogPost): RedirectResponse
    {
        return $this->save($request, $storeBlogPost);
    }

    public function destroy(StoreBlogPost $storeBlogPost): RedirectResponse
    {
        $this->blog->deletePost($storeBlogPost);

        return redirect()->route('store_admin.blog.index')->with('status', "“{$storeBlogPost->title}” silindi.");
    }

    public function categories(Request $request): RedirectResponse
    {
        $this->blog->saveCategories((array) $request->input('categories', []));

        return back()->with('status', 'Blog kategorileri kaydedildi.');
    }

    public function cover(StoreBlogPost $storeBlogPost): StreamedResponse
    {
        abort_unless($storeBlogPost->cover_path && Storage::disk('local')->exists($storeBlogPost->cover_path), 404);

        return Storage::disk('local')->response($storeBlogPost->cover_path, null, ['Content-Type' => 'image/webp', 'Cache-Control' => 'private, max-age=86400']);
    }

    private function save(Request $request, ?StoreBlogPost $post): RedirectResponse
    {
        $request->validate(['cover' => ['nullable', 'file', 'max:8192']], [], ['cover' => 'kapak görseli']);
        try {
            $post = $this->blog->savePost($post, $request->only(self::FIELDS) + ['is_published' => $request->boolean('is_published')],
                $request->file('cover'), $request->boolean('remove_cover'));
        } catch (DomainException $e) {
            return back()->withErrors(['cover' => $e->getMessage()])->withInput();
        }

        return redirect()->route('store_admin.blog.edit', $post)->with('status', 'Yazı kaydedildi.');
    }

    /** @return array<int|string, string> */
    private function categoryOptions(): array
    {
        return ['' => 'Kategorisiz'] + StoreBlogCategory::orderBy('sort')->orderBy('id')->pluck('name', 'id')->all();
    }
}
