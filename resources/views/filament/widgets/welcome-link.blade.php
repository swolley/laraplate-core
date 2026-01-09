<x-filament-widgets::widget class="fi-section-content">
    <x-filament::section>
        {{-- <div class="fi-documentation-widget-icon"> --}}
            <x-filament::icon 
                icon="heroicon-o-book-open" 
                class="fi-avatar fi-circular fi-size-lg fi-user-avatar"
            />
        {{-- </div> --}}

        <div class="fi-documentation-widget-main">
            <h2 class="fi-documentation-widget-heading">
                Documentation
            </h2>

            <p 
                class="fi-documentation-widget-descriptionphp "
            >
                View Welcome Page
            </p>
        </div>

        <div class="fi-documentation-widget-action">
            <x-filament::button
                color="gray"
                :icon="\Filament\Support\Icons\Heroicon::ArrowRight"
                labeled-from="sm"
                tag="a"
                :href="$welcome_url"
            >
                Open
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

<style>
.fi-documentation-widget-main {
    flex: 1;
}

.fi-documentation-widget-heading:where(.dark,.dark *) {
    color: var(--color-white);
}

.fi-documentation-widget-heading {
    font-size: var(--text-base);
    line-height: var(--tw-leading, var(--text-base--line-height));
    --tw-leading: calc(var(--spacing) * 6);
    line-height: calc(var(--spacing) * 6);
    --tw-font-weight: var(--font-weight-semibold);
    font-weight: var(--font-weight-semibold);
    color: var(--gray-950);
    flex: 1;
    display: grid;
}

.fi-documentation-widget-description:where(.dark,.dark *) {
    color: var(--gray-400);
}
.fi-documentation-widget-description {
    font-size: var(--text-sm);
    line-height: var(--tw-leading, var(--text-sm--line-height));
    color: var(--gray-500);
}

.fi-documentation-widget-link {
    @apply text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 transition-colors;
} */
</style>

