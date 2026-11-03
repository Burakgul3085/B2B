@props(['src', 'alt' => '', 'zoom' => false, 'eager' => false])
{{--
    Ürün görseli beyaz zeminde, kırpılmadan ve kenarlardan pay bırakılarak ortalanır.
    mix-blend-multiply görselin beyaz fonunu zeminle birleştirir: beyaz fonlu ürün fotoğraflarında
    "kutu içinde kutu" görünmez, ürün kartın üzerinde duruyormuş gibi görünür. isolate, karışımı bu
    alanla sınırlar.
    eager: sayfanın ilk ekranındaki kartlar (ör. listenin ilk 4 ürünü) beklemeden ve öncelikli yüklenir; diğerleri
    ekrana yaklaşınca yüklenir. decoding="async": görselin çözülmesi sayfanın çizimini bekletmez.
--}}
<span {{ $attributes->merge(['class' => 'isolate flex size-full items-center justify-center bg-[#fff] p-[4%]']) }}>
    <img src="{{ $src }}" alt="{{ $alt }}" @if ($eager) fetchpriority="high" @else loading="lazy" @endif decoding="async" @class(['max-h-full max-w-full object-contain mix-blend-multiply', 'transition duration-300 group-hover:scale-105' => $zoom])>
</span>
