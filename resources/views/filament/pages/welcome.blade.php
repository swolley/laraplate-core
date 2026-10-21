@use(\Modules\Core\Filament\Pages\Swagger)

<x-filament-panels::page>
    <div class="fi-page-content p-6">
        @php
            $grouped_modules = $this->getGroupedModules();
            $translations = $this->getTranslations();
        @endphp

        <div class="prose dark:prose-invert max-w-none">
            <main class="mt-6">
                <div class="grid gap-6 lg:grid-cols-2 lg:gap-8">
                    <div id="docs-card" class="flex flex-col items-start gap-4 overflow-hidden rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#a5ac56] dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#a5ac56]">
                        @php
                            $app_info = $grouped_modules['App'] ?? [];
                        @endphp
                        <div class="flex items-start justify-between gap-4 w-full">
                            <div class="flex flex-col gap-1 min-w-0">
                                <div class="flex items-baseline gap-3">
                                    <h2 class="text-xl font-semibold text-black dark:text-white">{{ config('app.name') }}</h2>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $app_info['version'] ?? 'N/A' }}</span>
                                </div>
                                @if (! empty($app_info['description']))
                                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $app_info['description'] }}</p>
                                @endif
                            </div>
                            <img class="w-12 h-12 shrink-0 ml-4 mr-2" src="{{ config('app.logo') }}" alt="" />
                        </div>

                        <div class="relative flex flex-col items-center gap-6 lg:items-end w-full">
                            <div id="docs-card-content" class="flex items-start gap-2 flex-col w-full">
                                <!-- language -->
                                <div class="text-sm/relaxed w-full">
                                    <i>{{ __('app.welcome.languages') }}</i>
                                    <span style="float: right">{{ implode(', ', $translations) }}</span>
                                </div>

                                <!-- active modules -->
                                <div class="text-sm/relaxed w-full">
                                    <i>{{ __('app.welcome.activeModules') }}</i>
                                    <span style="float: right">{{ implode(', ', modules(true)) }}</span>
                                </div>

                                <!-- migrations -->
                                <div class="text-sm/relaxed w-full">
                                    @php
                                    $done = migrations(true, true);
                                    $total = migrations(true, false);
                                    @endphp
                                    <i>{{ __('app.welcome.migrations') }}</i>
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
                                    <i>{{ __('app.welcome.apiVersions') }}</i>
                                    <span style="float: right">{{ implode(', ', api_versions()) ?: 'v1' }}</span>
                                </div>

                                <!-- debug mode -->
                                <div class="text-sm/relaxed w-full">
                                    @php
                                    $debug = config('app.debug');
                                    @endphp
                                    <i>{{ __('app.welcome.debugMode') }}</i>
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
                                    <i>{{ __('app.welcome.environment') }}</i>
                                    <span class="flex items-center" style="float: right">{{ config('app.env') }}</span>
                                </div>

                                <!-- database -->
                                <div class="text-sm/relaxed w-full">
                                    <i>{{ __('app.welcome.databaseDrivers') }}</i>
                                    <span class="flex items-center" style="float: right">{{ implode(', ', connections()) }}</span>
                                </div>

                                <!-- user registration -->
                                <div class="text-sm/relaxed w-full">
                                    <i>{{ __('app.welcome.userRegistration') }}</i>
                                    <div class="flex items-center" style="float: right">
                                        @if (config('core.auth.registration.enabled'))
                                            <x-core::check-icon style="color: var(--primary-400);" />
                                        @else
                                            <x-core::cancel-icon style="color: var(--color-gray-600);" />
                                        @endif
                                    </div>
                                </div>

                                <!-- user verification -->
                                <div class="text-sm/relaxed w-full">
                                    <i>{{ __('app.welcome.verifyNewUser') }}</i>
                                    <div class="flex items-center" style="float: right">
                                        @if (config('core.auth.email_verification.enabled'))
                                            <x-core::check-icon style="color: var(--primary-400);" />
                                        @else
                                            <x-core::cancel-icon style="color: var(--color-gray-600);" />
                                        @endif
                                    </div>
                                </div>

                                <!-- dynamic entities -->
                                <div class="text-sm/relaxed w-full">
                                    <i>{{ __('app.welcome.dynamicEntities') }}</i>
                                    <div class="flex items-center" style="float: right">
                                        @if (config('core.crud.dynamic_entities'))
                                            <x-core::check-icon style="color: var(--primary-400);" />
                                        @else
                                            <x-core::cancel-icon style="color: var(--color-gray-600);" />
                                        @endif
                                    </div>
                                </div>

                                <!-- maintenance -->
                                <div class="text-sm/relaxed w-full">
                                    <i>{{ __('app.welcome.maintenanceMode') }}</i>
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

