{{-- Mesaj metni ve ekler --}}
<div>
    <label for="body" class="mb-1.5 block text-sm font-medium">Mesaj <span class="text-danger">*</span></label>
    <textarea id="body" name="body" rows="4" required maxlength="5000" class="field-input" aria-invalid="{{ $errors->has('body') ? 'true' : 'false' }}">{{ old('body') }}</textarea>
    @error('body')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
</div>
<div>
    <input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png" class="text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm" aria-label="Ek dosyalar">
    <p class="mt-1 text-xs text-muted">İsteğe bağlı: en fazla 3 dosya (PDF, JPG, PNG; her biri 5 MB).</p>
    @error('files')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
    @error('files.*')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
</div>
