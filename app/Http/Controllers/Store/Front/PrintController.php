<?php

namespace App\Http\Controllers\Store\Front;

use App\Domain\Store\Cart\CartService;
use App\Domain\Store\Print\PrintCart;
use App\Domain\Store\Print\PrintFiles;
use App\Domain\Store\Print\PrintOptions;
use App\Domain\Store\Print\PrintSettings;
use App\Domain\Support\Money;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Store\Front\Concerns\CurrentCart;
use App\Models\Store\StorePrintFile;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fotokopi sayfası: belge yükleme (parçalı), sayfa sayısı ve önizleme, seçeneklerle canlı fiyat, sepete ekleme.
 * Belgeye yalnızca aynı sepet erişir; fiyat her istekte sunucuda hesaplanır (PrintCart).
 */
class PrintController extends Controller
{
    public function __construct(private CurrentCart $current, private PrintSettings $settings, private PrintFiles $files) {}

    public function show(Request $request): View
    {
        abort_unless($this->settings->enabled(), 404);
        $cart = $this->current->find($request);
        $files = $cart ? StorePrintFile::where('cart_id', $cart->id)->whereIn('status', ['processing', 'ready', 'failed'])
            ->where('updated_at', '>', now()->subDay())->orderBy('id')->get() : collect();

        return view('store.front.print', [
            'settings' => $this->settings->all(),
            'files' => $files->map(fn ($f) => $this->state($f))->values()->all(),
            'accept' => implode(',', array_map(fn ($e) => '.'.$e, array_keys(PrintFiles::TYPES))),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'size' => ['required', 'integer', 'min:1'], 'consent' => ['accepted'],
        ], ['consent.accepted' => 'Belgeyi çoğaltma hakkınız olduğunu onaylayın.']);
        $cart = $this->current->findOrCreate($request);
        try {
            $file = $this->files->start($cart, $data['name'], (int) $data['size']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        $this->current->remember($cart);

        return response()->json($this->state($file) + ['chunk' => (int) config('store.print.chunk_bytes')]);
    }

    public function chunk(Request $request, string $token): JsonResponse
    {
        $file = $this->own($request, $token);
        $request->validate(['offset' => ['required', 'integer', 'min:0'], 'chunk' => ['required', 'file', 'max:'.((int) config('store.print.chunk_bytes') / 1024 + 64)]]);
        try {
            $file = $this->files->appendChunk($file, (int) $request->input('offset'), $request->file('chunk'));
        } catch (DomainException $e) {
            if (str_starts_with($e->getMessage(), 'offset:')) {
                return response()->json(['received' => (int) substr($e->getMessage(), 7)], 409);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->state($file));
    }

    public function status(Request $request, string $token): JsonResponse
    {
        return response()->json($this->state($this->own($request, $token)));
    }

    public function preview(Request $request, string $token): Response
    {
        $file = $this->own($request, $token);
        abort_unless($file->preview_path && Storage::disk('local')->exists($file->preview_path), 404);

        return Storage::disk('local')->response($file->preview_path, null, ['Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Request $request, string $token): JsonResponse
    {
        $this->files->delete($this->own($request, $token));

        return response()->json(['deleted' => true]);
    }

    public function quote(Request $request, string $token, PrintCart $prints): JsonResponse
    {
        try {
            ['quote' => $quote, 'options' => $options] = $prints->quote($this->own($request, $token), $request->all());
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['quote' => $quote, 'total' => Money::format($quote['total']), 'summary' => $options->summary($quote['pages'])]);
    }

    public function add(Request $request, string $token, PrintCart $prints, CartService $carts): JsonResponse
    {
        $file = $this->own($request, $token);
        $cart = $this->current->findOrCreate($request);
        try {
            $prints->add($cart, $file, $request->all());
        } catch (ValidationException $e) {
            return response()->json(['message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['added' => true, 'count' => $carts->count($cart), 'cart_url' => route('store.cart')]);
    }

    private function own(Request $request, string $token): StorePrintFile
    {
        $cart = $this->current->find($request);
        abort_unless($cart && $this->settings->enabled(), 404);

        return StorePrintFile::where('token', $token)->where('cart_id', $cart->id)->where('status', '!=', 'deleted')->firstOrFail();
    }

    /** Tarayıcıya giden belge durumu. */
    private function state(StorePrintFile $file): array
    {
        return [
            'token' => $file->token, 'name' => $file->original_name, 'size' => $file->size, 'received' => $file->received,
            'status' => $file->status, 'pages' => $file->pages, 'error' => $file->error,
            'preview' => $file->preview_path ? route('store.print.preview', $file->token) : null,
            'image' => $file->isImage(),
            'layouts' => array_keys(PrintOptions::LAYOUTS),
        ];
    }
}
