@props(['src', 'alt' => '', 'zoom' => false])
{{--
    Ürün görseli beyaz zeminde, kırpılmadan ve kenarlardan pay bırakılarak ortalanır.
    mix-blend-multiply görselin beyaz fonunu zeminle birleştirir: beyaz fonlu ürün fotoğraflarında
    "kutu içinde kutu" görünmez, ürün kartın üzerinde duruyormuş gibi görünür. isolate, karışımı bu
    alanla sınırlar.
--}}
<span {{ $attributes->merge(['class' => 'isolate flex size-full items-center justify-center bg-white p-[4%]']) }}>
    <img src="{{ $src }}" alt="{{ $alt }}" loading="lazy" @class(['max-h-full max-w-full object-contain mix-blend-multiply', 'transition duration-300 group-hover:scale-105' => $zoom])>
</span>
