{{-- Bot koruması (App\Http\Middleware\StoreBotGuard): görünmez tuzak alanı + formun gösterildiği zaman. --}}
<div aria-hidden="true" class="absolute -left-[9999px] h-px w-px overflow-hidden">
    <label>Web siteniz <input type="text" name="website" value="" tabindex="-1" autocomplete="off"></label>
</div>
<input type="hidden" name="_ft" value="{{ \Illuminate\Support\Facades\Crypt::encryptString((string) time()) }}">
@error('form')<p role="alert" class="rounded-lg border border-red-200 bg-danger-soft px-4 py-3 text-sm text-red-900">{{ $message }}</p>@enderror
