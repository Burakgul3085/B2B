@props(['icon' => 'inbox', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-12 text-center']) }}>
    <div class="mb-4 flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary">
        <x-icon :name="$icon" class="size-6" />
    </div>
    <h3 class="text-base font-semibold text-ink">{{ $title }}</h3>
    @if ($text)<p class="mt-1 max-w-md text-sm text-muted">{{ $text }}</p>@endif
    @if ($slot->isNotEmpty())<div class="mt-5">{{ $slot }}</div>@endif
</div>
