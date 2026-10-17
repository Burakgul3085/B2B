{{-- Duyuru ekleri: $announcement, $route (indirme rotası adı) --}}
@if ($announcement->attachments->isNotEmpty())
    <ul class="mt-3 flex flex-wrap gap-2">
        @foreach ($announcement->attachments as $file)
            @php
                $ext = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
                $tone = match ($ext) {
                    'pdf' => 'bg-red-50 text-red-700 ring-red-200',
                    'doc', 'docx' => 'bg-blue-50 text-blue-700 ring-blue-200',
                    'xls', 'xlsx' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                    default => 'bg-amber-50 text-amber-700 ring-amber-200',
                };
                $size = $file->size >= 1048576 ? number_format($file->size / 1048576, 1, ',', '.').' MB' : max(1, (int) round($file->size / 1024)).' KB';
            @endphp
            <li>
                <a href="{{ route($route, [$announcement, $file]) }}" class="group inline-flex max-w-xs items-center gap-2.5 rounded-lg border border-line bg-white py-1.5 pr-3 pl-1.5 text-sm shadow-xs transition hover:border-primary/40 hover:shadow-sm">
                    <span class="flex h-7 min-w-9 items-center justify-center rounded-md px-1 text-[10px] font-bold uppercase ring-1 {{ $tone }}">{{ $ext }}</span>
                    <span class="min-w-0">
                        <span class="block truncate font-medium text-ink group-hover:text-primary">{{ $file->original_name }}</span>
                        <span class="block text-[11px] text-muted">{{ $size }}</span>
                    </span>
                    <x-icon name="download" class="size-4 shrink-0 text-muted group-hover:text-primary" />
                </a>
            </li>
        @endforeach
    </ul>
@endif
