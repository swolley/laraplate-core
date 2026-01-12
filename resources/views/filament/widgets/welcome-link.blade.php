<x-filament-widgets::widget class="fi-documentation-widget">
    <x-filament::section>
        <div class="flex items-center">
            {{-- <div class="fi-documentation-widget-icon"> --}}
                {{-- <x-filament::icon 
                    icon="heroicon-o-book-open" 
                    class="fi-avatar fi-circular fi-size-lg fi-user-avatar"
                /> --}}
            {{-- </div> --}}

            <div class="fi-documentation-widget-main grow">
                <h2 class="fi-documentation-widget-heading dark:text-white" style="font-size: var(--text-base);
                    line-height: var(--tw-leading,var(--text-base--line-height));
                    --tw-leading: calc(var(--spacing)*6);
                    line-height: calc(var(--spacing)*6);
                    --tw-font-weight: var(--font-weight-semibold);
                    font-weight: var(--font-weight-semibold);"
                >
                    Documentation
                </h2>

                <p class="fi-documentation-widget-description" style="color: var(--gray-400); 
                    font-size: var(--text-sm);
                    line-height: var(--tw-leading,var(--text-sm--line-height))"
                >
                    View Welcome Page
                </p>
            </div>

            <div class="fi-documentation-widget-action">
                <x-filament::button
                    color="gray"
                    :icon="\Filament\Support\Icons\Heroicon::OutlinedBookOpen"
                    labeled-from="sm"
                    tag="a"
                    :href="$welcome_url"
                >
                    Open
                </x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

