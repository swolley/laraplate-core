<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-clock" class="w-5 h-5" />
                Recent Activity
            </div>
        </x-slot>

        <div class="fi-section-content">
            <div class="grid gap-6 md:grid-cols-2">
                @if(!empty($recent_contents))
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Recent Contents</h3>
                        <div class="space-y-2">
                            @foreach($recent_contents as $content)
                                <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-800">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                            {{ $content['title'] }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $content['created_at'] }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if(!empty($recent_users))
                    <div>
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">Recent Users</h3>
                        <div class="space-y-2">
                            @foreach($recent_users as $user)
                                <div class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-800">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                            {{ $user['name'] }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ $user['email'] }} · {{ $user['created_at'] }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            @if(empty($recent_contents) && empty($recent_users))
                <div class="fi-alert fi-color-gray fi-size-sm">
                    <div class="fi-alert-content">
                        No recent activity found.
                    </div>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

