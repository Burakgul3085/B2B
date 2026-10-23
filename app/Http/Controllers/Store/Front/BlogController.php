<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Storefront\Catalog;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreBlogCategory;
use App\Models\Store\StoreBlogPost;
use Illuminate\View\View;

/** Blog: yazı listesi (kategoriye göre) ve yazı sayfası. Yalnızca yayındaki ve tarihi gelmiş yazılar görünür. */
class BlogController extends Controller
{
    public const PER_PAGE = 12;

    public function index(): View
    {
        return $this->list(null);
    }

    public function category(string $slug): View
    {
        return $this->list(StoreBlogCategory::where('slug', $slug)->firstOrFail());
    }

    public function show(string $slug, Catalog $catalog): View
    {
        $post = StoreBlogPost::live()->with('category')->where('slug', $slug)->firstOrFail();
        $productIds = $post->products()->pluck('store_products.id')->all();

        return view('store.front.blog.show', [
            'post' => $post,
            'products' => $productIds ? $catalog->forCards($catalog->products()->whereIn('id', $productIds))->get()
                ->sortBy(fn ($p) => array_search($p->id, $productIds, true))->values() : collect(),
            // Aynı kategoriden olanlar önce
            'more' => StoreBlogPost::live()->with('category:id,name,slug')->whereKeyNot($post->id)
                ->orderByRaw('category_id <=> ? DESC', [$post->category_id])->latest('published_at')->limit(3)->get(),
        ]);
    }

    private function list(?StoreBlogCategory $category): View
    {
        return view('store.front.blog.index', [
            'category' => $category,
            'categories' => StoreBlogCategory::whereHas('posts', fn ($q) => $q->live())->orderBy('sort')->orderBy('id')->get(),
            'posts' => StoreBlogPost::live()->with('category:id,name,slug')
                ->when($category, fn ($q) => $q->where('category_id', $category->id))
                ->latest('published_at')->orderByDesc('id')->paginate(self::PER_PAGE),
        ]);
    }
}
