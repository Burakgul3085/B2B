{{-- Bir mağaza yöneticisi hesabını yönetme formları (mağaza panelinde süper admin, platformda platform yöneticisi): $target, $routes --}}
<div class="grid gap-6 lg:grid-cols-2">
    <x-card title="Bilgiler">
        <form method="POST" action="{{ $routes['update'] }}" class="space-y-5">
            @csrf @method('PUT')
            <x-field name="name" label="Ad soyad" :value="$target->name" required />
            <x-field name="email" label="Giriş e-postası" type="email" :value="$target->email" required autocomplete="off" />
            <x-button>Kaydet</x-button>
        </form>
    </x-card>

    <x-card title="Yeni şifre belirle" description="Kişinin açık oturumları ve sıfırlama bağlantıları geçersiz olur.">
        <form method="POST" action="{{ $routes['password'] }}" class="space-y-5">
            @csrf @method('PUT')
            <x-field name="password" label="Yeni şifre" type="password" required autocomplete="new-password" hint="En az 10 karakter; harf ve rakam içermeli." />
            <x-field name="password_confirmation" label="Yeni şifre (tekrar)" type="password" required autocomplete="new-password" />
            <x-button variant="secondary">Şifreyi belirle</x-button>
        </form>
    </x-card>

    <x-card title="Hesap durumu" :description="$target->is_active ? 'Pasife alınan kişi panele giremez; açık oturumu hemen kapanır. İşlem geçmişi korunur.' : 'Hesap pasif; kişi panele giremez.'">
        <form method="POST" action="{{ $routes['status'] }}" @if ($target->is_active) data-confirm="{{ $target->name }} pasife alınsın mı?" @endif>
            @csrf @method('PUT')
            <input type="hidden" name="active" value="{{ $target->is_active ? 0 : 1 }}">
            <x-button :variant="$target->is_active ? 'danger' : 'primary'">{{ $target->is_active ? 'Pasife al' : 'Kullanıma aç' }}</x-button>
        </form>
    </x-card>
</div>
