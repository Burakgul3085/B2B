{{-- Başvuru onayında bayinin giriş bilgileri (toptancı ve platform ortak). --}}
<x-field name="login_email" label="Giriş e-postası" type="email" :value="old('login_email', $application->email)" required autocomplete="off"
    hint="Başvurudaki adres; gerekirse düzeltin. Bu e-postayla kayıtlı bayi hesabı varsa mevcut hesaba erişim eklenir." />
<div x-data="{ show: false }">
    <label for="approve_password" class="mb-1.5 block text-sm font-medium">Şifre</label>
    <div class="flex gap-2">
        <input id="approve_password" name="password" :type="show ? 'text' : 'password'" autocomplete="new-password" class="field-input" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
        <x-button type="button" variant="secondary" size="sm" @click="show = !show" x-text="show ? 'Gizle' : 'Göster'">Göster</x-button>
    </div>
    <p class="mt-1.5 text-xs text-muted">Yeni hesap için zorunlu; en az 10 karakter, harf ve rakam. Şifreyi bayiye siz iletirsiniz; bayi giriş yaptıktan sonra değiştirebilir.</p>
    @error('password')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
</div>
