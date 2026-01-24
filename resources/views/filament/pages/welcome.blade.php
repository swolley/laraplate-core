@use(\Modules\Core\Filament\Pages\Swagger)

<x-filament-panels::page>
    <div class="fi-page-content p-6">
        @php
            $grouped_modules = $this->getGroupedModules();
            $translations = $this->getTranslations();
        @endphp

        <header class="grid items-center gap-2 py-10 max-w-md mx-auto">
            <div class="flex justify-center w-auto text-[#a5ac56] gap-2">
                <img src="{{ config('app.logo') }}" />
            </div>
        </header>

        <div class="prose dark:prose-invert max-w-none">
            <main class="mt-6">
                <div class="grid gap-6 lg:grid-cols-2 lg:gap-8">
                    <div id="docs-card" class="flex flex-col items-start gap-4 overflow-hidden rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#a5ac56] dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#a5ac56]">
                        <div>
                            <h2 class="text-xl font-semibold text-black dark:text-white">{{ config('app.name') }}</h2>
                        </div>

                        <div class="relative flex flex-col items-center gap-6 lg:items-end w-full">
                            <div id="docs-card-content" class="flex items-start gap-2 flex-col w-full">
                                <!-- language -->
                                <div class="text-sm/relaxed w-full">
                                    <i>Languages:</i>
                                    <span style="float: right">{{ implode(', ', $translations) }}</span>
                                </div>

                                <!-- active modules -->
                                <div class="text-sm/relaxed w-full">
                                    <i>Active Modules:</i>
                                    <span style="float: right">{{ implode(', ', modules(true)) }}</span>
                                </div>

                                <!-- migrations -->
                                <div class="text-sm/relaxed w-full">
                                    @php
                                    $done = migrations(true, true);
                                    $total = migrations(true, false);
                                    @endphp
                                    <i>Migrations:</i>
                                    <div class="ml-4 flex items-center gap-2" style="float: right">
                                        @if ($done !== $total)
                                            <x-core::alert-icon style="color: var(--warning-400);" />
                                        @else
                                            <x-core::check-icon style="color: var(--primary-400);" />
                                        @endif
                                        <span>{{ migrations(true, true) }} / {{ migrations(true, false) }}</span>
                                    </div>
                                </div>

                                <!-- api versions -->
                                <div class="text-sm/relaxed w-full">
                                    <i>API Versions:</i>
                                    <span style="float: right">{{ implode(', ', api_versions()) ?: 'v1' }}</span>
                                </div>

                                <!-- debug mode -->
                                <div class="text-sm/relaxed w-full">
                                    @php
                                    $debug = config('app.debug');
                                    @endphp
                                    <i>Debug Mode:</i>
                                    <div class="flex items-center gap-2" style="float: right; {{ $debug ? 'color: var(--warning-400);' : 'color: var(--color-gray-600);' }}">
                                        @if ($debug)
                                            <x-core::alert-icon />
                                        @else
                                            <x-core::cancel-icon />
                                        @endif
                                    </div>
                                </div>

                                <!-- environment -->
                                <div class="text-sm/relaxed w-full">
                                    <i>Application Environment:</i>
                                    <span class="flex items-center" style="float: right">{{ config('app.env') }}</span>
                                </div>

                                <!-- database -->
                                <div class="text-sm/relaxed w-full">
                                    <i>Database Drivers:</i>
                                    <span class="flex items-center" style="float: right">{{ implode(', ', connections()) }}</span>
                                </div>

                                <!-- user registration -->
                                <div class="text-sm/relaxed w-full">
                                    <i>Enable User registration:</i>
                                    <div class="flex items-center" style="float: right">
                                        @if (config('core.enable_user_registration'))
                                            <x-core::check-icon style="color: var(--primary-400);" />
                                        @else
                                            <x-core::cancel-icon style="color: var(--color-gray-600);" />
                                        @endif
                                    </div>
                                </div>

                                <!-- user verification -->
                                <div class="text-sm/relaxed w-full">
                                    <i>Verify new user:</i>
                                    <div class="flex items-center" style="float: right">
                                        @if (config('core.verify_new_user'))
                                            <x-core::check-icon style="color: var(--primary-400);" />
                                        @else
                                            <x-core::cancel-icon style="color: var(--color-gray-600);" />
                                        @endif
                                    </div>
                                </div>

                                <!-- dynamic entities -->
                                <div class="text-sm/relaxed w-full">
                                    <i>Enable dynamic entities:</i>
                                    <div class="flex items-center" style="float: right">
                                        @if (config('crud.dynamic_entities'))
                                            <x-core::check-icon style="color: var(--primary-400);" />
                                        @else
                                            <x-core::cancel-icon style="color: var(--color-gray-600);" />
                                        @endif
                                    </div>
                                </div>

                                <!-- maintenance -->
                                <div class="text-sm/relaxed w-full">
                                    <i>Maintenance mode:</i>
                                    <div class="flex items-center" style="float: right">
                                        @if (app()->isDownForMaintenance())
                                            <x-core::alert-icon style="color: var(--warning-400);" />
                                        @else
                                            <x-core::cancel-icon style="color: var(--color-gray-600);" />
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- modules -->
                    @foreach ($grouped_modules as $module => $data)
                        <x-core::module :module="$module" :data="$data" />
                    @endforeach
                </div>
            </main>
        </div>
    </div>
</x-filament-panels::page>

