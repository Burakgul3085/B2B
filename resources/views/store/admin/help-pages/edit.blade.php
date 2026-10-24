@php
    use App\Domain\Store\Content\HelpPages;

    $c = $page['content'];
    $tiles = old('tiles', $c['tiles'] ?? []);
    $items = old('items', $c['items'] ?? []);
@endphp
<x-layouts.panel :area="\App\Domain\Auth\Area::StoreAdmin" :title="$c['title']">
    <x-page-header :title="$c['title']" :subtitle="$meta[2]">
        <x-slot:breadcrumb><a href="{{ route('store_admin.help-pages.index') }}" class="hover:underline">Yardım Sayfaları</a></x-slot:breadcrumb>
        @if ($page['published'])<x-button :href="route('store.help.'.$key)" variant="secondary" icon="eye" target="_blank">Mağazada gör</x-button>@endif
    </x-page-header>

    <form method="POST" action="{{ route('store_admin.help-pages.update', $key) }}" class="grid gap-6 xl:grid-cols-3">
        @csrf @method('PUT')
        <div class="space-y-6 xl:col-span-2">
            <x-card>
                <div class="space-y-5">
                    <x-field name="title" label="Sayfa başlığı" :value="old('title', $c['title'])" required maxlength="80" />
                    <x-field name="intro" label="Giriş metni" textarea :value="old('intro', $c['intro'])" maxlength="400" hint="Başlığın altında görünür." />
                </div>
            </x-card>

            @if ($key === 'help')
                <x-card title="Yardım kutuları" description="En fazla {{ HelpPages::MAX_TILES }} kutu. Başlığı boş bırakılan kutu görünmez. Bağlantı olarak listeden bir sayfa seçin.">
                    <div class="space-y-3">
                        @for ($i = 0; $i < HelpPages::MAX_TILES; $i++)
                            @php $tile = $tiles[$i] ?? ['icon' => 'check-circle', 'title' => '', 'text' => '', 'link' => '']; @endphp
                            <div class="grid gap-3 rounded-lg bg-surface p-4 sm:grid-cols-[9rem_1fr_1fr]">
                                <x-field :name="'tiles['.$i.'][icon]'" label="Simge" :options="$icons" :value="$tile['icon'] ?? 'check-circle'" />
                                <x-field :name="'tiles['.$i.'][title]'" label="Başlık" :value="$tile['title'] ?? ''" maxlength="60" />
                                <div>
                                    <label class="mb-1.5 block text-sm font-medium" for="tile-link-{{ $i }}">Bağlantı</label>
                                    <select id="tile-link-{{ $i }}" name="tiles[{{ $i }}][link]" class="field-input">
                                        <option value="">— Yok —</option>
                                        @foreach ($linkOptions as $group => $options)
                                            <optgroup label="{{ $group }}">
                                                @foreach ($options as $value => $label)
                                                    <option value="{{ $value }}" @selected(($tile['link'] ?? '') === $value)>{{ $label }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                    @error('tiles.'.$i.'.link')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
                                </div>
                                <x-field :name="'tiles['.$i.'][text]'" label="Kısa açıklama" :value="$tile['text'] ?? ''" maxlength="140" class="sm:col-span-3" />
                            </div>
                        @endfor
                    </div>
                </x-card>
            @endif

            @if ($key === 'faq')
                <x-card title="Sorular ve cevaplar" description="Grup adı aynı olan sorular birlikte listelenir (ör. Sipariş, Teslimat, Ödeme, İade). Cevapta **kalın** yazı ve [bağlantı](https://…) kullanabilirsiniz. Soru ve cevabı boş satır kaydedilmez.">
                    <div class="space-y-3" x-data="{ shown: {{ max(5, count($items) + 2) }} }">
                        @for ($i = 0; $i < HelpPages::MAX_FAQ; $i++)
                            @php $item = $items[$i] ?? ['group' => '', 'question' => '', 'answer' => '']; @endphp
                            <div class="grid gap-3 rounded-lg bg-surface p-4 sm:grid-cols-[10rem_1fr]" x-show="shown > {{ $i }}" @if ($i >= max(5, count($items) + 2)) x-cloak @endif>
                                <x-field :name="'items['.$i.'][group]'" label="Grup" :value="$item['group'] ?? ''" maxlength="40" placeholder="Genel" />
                                <x-field :name="'items['.$i.'][question]'" :label="($i + 1).'. soru'" :value="$item['question'] ?? ''" maxlength="200" />
                                <x-field :name="'items['.$i.'][answer]'" label="Cevap" textarea :value="$item['answer'] ?? ''" maxlength="3000" class="sm:col-span-2" />
                                @error('items.'.$i)<p class="text-sm text-danger sm:col-span-2">{{ $message }}</p>@enderror
                            </div>
                        @endfor
                        <button type="button" x-show="shown < {{ HelpPages::MAX_FAQ }}" @click="shown = shown + 5" class="text-sm font-medium text-primary hover:underline">+ Daha fazla soru alanı</button>
                    </div>
                </x-card>
            @endif

            <x-card :title="in_array($key, ['shipping', 'payment'], true) ? 'Ek açıklama' : 'Sayfa metni'" description="İsteğe bağlı. Paragraflar arasında boş satır bırakın; ara başlık için satır başına “## ”, madde için “- ” yazın.">
                <div x-data="markdownToolbar()">
                    <div class="mb-2 flex flex-wrap gap-1.5 text-sm">
                        <button type="button" class="rounded-md border border-line px-2.5 py-1 font-bold hover:bg-slate-50" @click="bold">K</button>
                        <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="heading">Ara başlık</button>
                        <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="bullet">• Madde</button>
                        <button type="button" class="rounded-md border border-line px-2.5 py-1 hover:bg-slate-50" @click="link">Bağlantı</button>
                    </div>
                    <textarea name="body" rows="10" x-ref="text" class="field-input text-sm leading-relaxed" maxlength="20000">{{ old('body', $c['body']) }}</textarea>
                    @error('body')<p class="mt-1.5 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Yayın">
                <div class="space-y-4">
                    <x-toggle name="is_published" label="Sayfa yayında" :checked="$page['published']" hint="Kapalıyken sayfa açılmaz ve bağlantıları alt bilgiden gizlenir." />
                    @if ($key === 'shipping')
                        <x-toggle name="show_rates" label="Ücret ve seçenek kartları" :checked="$c['show_rates']" hint="Kargo ücreti, ücretsiz kargo sınırı, aynı gün kargo saati ve Eskişehir teslimatı Ödeme ve Teslimat ayarlarından yazılır." />
                    @endif
                    @if ($key === 'payment')
                        <x-toggle name="show_methods" label="Ödeme yöntemi kartları" :checked="$c['show_methods']" hint="Kart logoları ve taksit kartları; açıksa havale/EFT hesapları ve kapıda ödeme." />
                    @endif
                    <x-button class="w-full justify-center">Kaydet</x-button>
                </div>
            </x-card>
        </div>
    </form>
</x-layouts.panel>
