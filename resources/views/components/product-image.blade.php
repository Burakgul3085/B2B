@props(['src', 'alt' => '', 'zoom' => false])
{{--
    Ürün görseli, kapsayıcısını boşluk bırakmadan doldurur ama hiç kırpılmaz:
    görsel kendi oranında ortalanır, kalan alan aynı görselin bulanık hâliyle dolar.
    Kare görsellerde bulanık katman görünmez. Aynı adres iki kez kullanıldığı için tek kez indirilir.
--}}
<span {{ $attributes->merge(['class' => 'relative block size-full overflow-hidden bg-slate-100']) }}>
    <img src="{{ $src }}" alt="" aria-hidden="true" loading="lazy" class="absolute inset-0 size-full scale-125 object-cover opacity-70 blur-2xl saturate-150">
    <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" @class(['relative size-full object-contain drop-shadow-sm', 'transition duration-300 group-hover:scale-105' => $zoom])>
</span>
