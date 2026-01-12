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
                                            @include('core::components.alert-icon')
                                        @else
                                            @include('core::components.check-icon')
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
                                    <div class="flex items-center  gap-2" style="float: right">
                                        @if ($debug)
                                            @include('core::components.alert-icon')
                                        @else
                                            @include('core::components.cancel-icon')
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
                                            @include('core::components.check-icon')
                                        @else
                                            @include('core::components.cancel-icon')
                                        @endif
                                    </div>
                                </div>

                                <!-- user verification -->
                                <div class="text-sm/relaxed w-full">
                                    <i>Verify new user:</i>
                                    <div class="flex items-center" style="float: right">
                                        @if (config('core.verify_new_user'))
                                            @include('core::components.check-icon')
                                        @else
                                            @include('core::components.cancel-icon')
                                        @endif
                                    </div>
                                </div>

                                <!-- dynamic entities -->
                                <div class="text-sm/relaxed w-full">
                                    <i>Enable dynamic entities:</i>
                                    <div class="flex items-center" style="float: right">
                                        @if (config('crud.dynamic_entities'))
                                            @include('core::components.check-icon')
                                        @else
                                            @include('core::components.cancel-icon')
                                        @endif
                                    </div>
                                </div>

                                <!-- maintenance -->
                                <div class="text-sm/relaxed w-full">
                                    <i>Maintenance mode:</i>
                                    <div class="flex items-center" style="float: right">
                                        @if (app()->isDownForMaintenance())
                                        @include('core::components.alert-icon')
                                        @else
                                        @include('core::components.cancel-icon')
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div id="docs-card-content" class="flex flex-col gap-2 w-full">
                                <a href="{{ Swagger::getUrl() }}" class="flex items-center justify-center gap-2">
                                    <div class="flex items-center justify-center" style="min-width:60px;">
                                        @include('core::components.swagger-icon')
                                    </div>
                                    <div class="text-sm w-full">Go to routes documentation</div>
                                    @include('core::components.arrow-icon')
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- modules -->
                    @foreach ($grouped_modules as $module => $data)
                        <div class="module {{ ! $data['isEnabled'] ? 'disabled' : '' }} flex items-start gap-4 rounded-lg bg-white p-6 shadow-[0px_14px_34px_0px_rgba(0,0,0,0.08)] ring-1 ring-white/[0.05] transition duration-300 hover:text-black/70 hover:ring-black/20 focus:outline-none focus-visible:ring-[#a5ac56] dark:bg-zinc-900 dark:ring-zinc-800 dark:hover:text-white/70 dark:hover:ring-zinc-700 dark:focus-visible:ring-[#a5ac56]">
                            @if(! $data['isEnabled'])
                                @include('core::components.cancel-icon')
                            @endif

                            <div class="w-full flex flex-col h-full">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-lg font-semibold text-black dark:text-white">{{ $module }}</h3>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $data['version'] ?? 'N/A' }}</span>
                                </div>

                                @if(isset($data['description']))
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">{{ $data['description'] }}</p>
                                @endif

                                @if(isset($data['routes']) && count($data['routes']) > 0)
                                    <div class="mt-auto">
                                        <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2">Routes:</p>
                                        <div class="flex flex-wrap gap-2">
                                            @foreach($data['routes'] as $route)
                                                <span class="px-2 py-1 text-xs bg-gray-100 dark:bg-gray-800 rounded">{{ $route }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </main>
        </div>
    </div>
</x-filament-panels::page>

