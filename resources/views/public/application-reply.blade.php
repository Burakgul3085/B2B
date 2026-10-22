<x-layouts.public :business="$business" title="Ek bilgi gönder">
    <div class="mt-2">
        <h1 class="text-2xl font-bold tracking-tight">Ek bilgi gönder</h1>
        <p class="mt-1 text-sm text-muted">{{ $application->legal_name }} adına yaptığınız bayilik başvurusu</p>
    </div>

    <x-card title="İstenen bilgiler" class="mt-6">
        <p class="whitespace-pre-line text-sm">{{ $application->info_request_note }}</p>
    </x-card>

    <form method="POST" action="{{ route('public.application.reply.store', $token) }}" enctype="multipart/form-data" class="mt-6 space-y-6">
        @csrf
        <x-card title="Yanıtınız">
            <div class="space-y-5">
                <x-field name="message" label="Açıklama" textarea required maxlength="3000" />
                <div>
                    <label for="documents" class="mb-1.5 block text-sm font-medium">Belgeler (isteğe bağlı, en fazla 3)</label>
                    <input id="documents" type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-medium">
                    <p class="mt-1.5 text-xs text-muted">PDF, JPG veya PNG; her biri en fazla 5 MB.</p>
                    @error('documents')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                    @error('documents.*')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-card>
        <div class="flex justify-end"><x-button icon="check">Gönder</x-button></div>
        <p class="text-xs text-muted">Bu bağlantı bir kez kullanılabilir ve {{ $application->reply_expires_at->timezone('Europe/Istanbul')->format('d.m.Y') }} tarihine kadar geçerlidir.</p>
    </form>
</x-layouts.public>
