<?php

namespace App\Http\Controllers\Store\Admin;

use App\Domain\Store\Orders\LegalDocuments;
use App\Http\Controllers\Controller;
use App\Models\Store\StoreLegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Yasal metinler (Ön Bilgilendirme Formu, Mesafeli Satış Sözleşmesi). Her kayıt yeni sürüm oluşturur. */
class LegalController extends Controller
{
    public function index(): View
    {
        return view('store.admin.storefront.legal-index', [
            'documents' => collect(StoreLegalDocument::KEYS)->map(fn ($title, $key) => [
                'current' => StoreLegalDocument::current($key),
                'versions' => StoreLegalDocument::where('key', $key)->count(),
            ]),
        ]);
    }

    public function edit(string $key): View
    {
        abort_unless(array_key_exists($key, StoreLegalDocument::KEYS), 404);

        return view('store.admin.storefront.legal-edit', [
            'key' => $key,
            'document' => StoreLegalDocument::current($key),
            'history' => StoreLegalDocument::where('key', $key)->orderByDesc('version')->get(['id', 'version', 'created_at']),
            'placeholders' => LegalDocuments::PLACEHOLDERS,
        ]);
    }

    public function update(Request $request, string $key, LegalDocuments $legal): RedirectResponse
    {
        abort_unless(array_key_exists($key, StoreLegalDocument::KEYS), 404);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'min:200', 'max:100000'],
        ], [], ['title' => 'başlık', 'body' => 'metin']);

        $current = StoreLegalDocument::current($key);
        if ($current->title === trim($data['title']) && $current->body === $data['body']) {
            return back()->with('status', 'Değişiklik yok; yeni sürüm oluşturulmadı.');
        }
        $doc = $legal->publish($key, $data['title'], $data['body']);

        return back()->with('status', "Sürüm {$doc->version} yayınlandı. Bundan sonraki siparişlerde bu metin onaylatılır.");
    }
}
