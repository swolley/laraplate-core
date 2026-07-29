@php
    use function Filament\Support\get_color_css_variables;
@endphp

<div
    x-data="{ open: false }"
    class="relative"
>
    <button
        type="button"
        @click="open = ! open"
        @keydown.escape.window="open = false"
        class="
            environment-indicator
            fi-badge fi-color fi-text-color-600
            dark:fi-text-color-400
            cursor-pointer
        "
        style="{{ get_color_css_variables($color, [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950]) }}"
        :aria-expanded="open.toString()"
        aria-haspopup="listbox"
    >
        {{ $environment }}
    </button>

    <div
        x-cloak
        x-show="open"
        x-transition.opacity.duration.150ms
        @click.outside="open = false"
        role="listbox"
        class="
            absolute end-0 z-50 mt-2 min-w-56 overflow-hidden rounded-lg
            bg-white shadow-lg ring-1 ring-gray-950/5
            dark:bg-gray-900 dark:ring-white/10
        "
    >
        <ul class="max-h-80 divide-y divide-gray-100 overflow-y-auto py-1 dark:divide-white/5">
            @foreach ($entries as $entry)
                <li
                    role="option"
                    class="flex items-center justify-between gap-4 px-3 py-2 text-sm
                        {{ $entry->enabled ? 'text-gray-950 dark:text-white' : 'text-gray-400 dark:text-gray-500' }}"
                >
                    <span class="font-medium">{{ $entry->name }}</span>
                    <span class="font-mono text-xs tabular-nums">{{ $entry->version }}</span>
                </li>
            @endforeach
        </ul>
    </div>
</div>
