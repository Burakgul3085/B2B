{{-- Havale / EFT hesap satırları (mağaza ve B2B ödeme ayarları): IBAN'dan banka ve logo tanınır (bankAccounts bileşeni). --}}
@php
    $rowErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'transfer_accounts.'))
        ->mapWithKeys(fn ($m, $k) => [substr($k, strlen('transfer_accounts.')) => $m[0]])->all();
    $oldRows = array_values(array_map(fn ($r) => ['bank' => (string) ($r['bank'] ?? ''), 'holder' => (string) ($r['holder'] ?? ''), 'iban' => (string) ($r['iban'] ?? '')], array_filter((array) old('transfer_accounts', []), 'is_array')));
@endphp
<fieldset x-data="bankAccounts(@alpine(old('transfer_accounts') !== null ? $oldRows : $bankRows), @alpine((object) $banks), @alpine((object) $rowErrors), @alpine($max))">
    <legend class="text-sm font-medium">Banka hesapları</legend>
    <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
    @error('transfer_accounts')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror

    <div class="mt-3 space-y-3">
        <template x-for="(row, i) in rows" :key="i">
            <div class="rounded-xl border border-line p-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-32 shrink-0 items-center justify-center rounded-lg bg-[#fff] px-2 ring-1 ring-line">
                        <img x-show="known(row)" :src="bankLogo(row)" :alt="bankName(row)" class="max-h-7 max-w-full object-contain">
                        <span x-show="!known(row)" class="flex size-8 items-center justify-center rounded-full bg-surface text-xs font-bold text-muted" x-text="initials(row)"></span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold" x-text="title(row)"></p>
                        <p class="text-xs text-muted" x-text="status(row)"></p>
                    </div>
                    <button type="button" class="rounded-lg p-2 text-muted hover:bg-danger-soft hover:text-danger" @click="remove(i)" aria-label="Hesabı kaldır" title="Hesabı kaldır"><x-icon name="trash" class="size-4" /></button>
                </div>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted">IBAN</span>
                        <input type="text" class="field-input font-mono tracking-wide" :name="'transfer_accounts[' + i + '][iban]'" x-model="row.iban" @blur="tidy(row)"
                            maxlength="40" inputmode="text" autocomplete="off" spellcheck="false" placeholder="TR00 0000 0000 0000 0000 0000 00">
                        <span class="mt-1 block text-xs text-danger" x-show="error(i, 'iban')" x-text="error(i, 'iban')"></span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs font-medium text-muted">Hesap sahibi</span>
                        <input type="text" class="field-input" :name="'transfer_accounts[' + i + '][holder]'" x-model="row.holder" maxlength="120" placeholder="Ad soyad veya şirket unvanı">
                        <span class="mt-1 block text-xs text-danger" x-show="error(i, 'holder')" x-text="error(i, 'holder')"></span>
                    </label>
                    <label class="block sm:col-span-2" x-show="unknown(row)">
                        <span class="mb-1 block text-xs font-medium text-muted">Banka adı</span>
                        <input type="text" class="field-input" :name="'transfer_accounts[' + i + '][bank]'" x-model="row.bank" maxlength="60" placeholder="Bankanın adı">
                        <span class="mt-1 block text-xs text-danger" x-show="error(i, 'bank')" x-text="error(i, 'bank')"></span>
                    </label>
                </div>
            </div>
        </template>
    </div>
    <button type="button" class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline disabled:cursor-not-allowed disabled:opacity-50" @click="add()" :disabled="!canAdd()">
        <x-icon name="plus" class="size-4" /> Hesap ekle
    </button>
    <span class="ml-2 text-xs text-muted">(en fazla {{ $max }})</span>
</fieldset>
