<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                {{-- <x-filament::icon icon="heroicon-o-clock" class="w-5 h-5" /> --}}
                Recent Activity
            </div>
        </x-slot>

        <div class="fi-section-content">
            {{-- <div class="grid gap-6 md:grid-cols-2"> --}}
                @if(!empty($recent_contents))
                    <div class="fi-table">
                        <table class="fi-ta-table">
                            <thead class="fi-ta-header">
                                <tr class="fi-ta-row">
                                    <th colspan="3" class="fi-ta-header-cell text-center">Recent Contents</th>
                                </tr>
                                <tr class="fi-ta-row">
                                    <th class="fi-ta-header-cell">Id</th>
                                    <th class="fi-ta-header-cell">Reference</th>
                                    <th class="fi-ta-header-cell">Created At</th>
                                </tr>
                            </thead>
                            <tbody class="fi-ta-body">
                                @foreach($recent_contents as $content)
                                    <tr class="fi-ta-row">
                                        <td class="fi-ta-cell">
                                            <div class="fi-ta-cell-content truncate text-right">
                                                {{ $content['title']['id'] }}
                                            </div>
                                        </td>
                                        <td class="fi-ta-cell">
                                            <div class="fi-ta-cell-content truncate">
                                                {{ $content['title']['title'] }}
                                            </div>
                                        </td>
                                        <td class="fi-ta-cell">
                                            <div class="fi-ta-cell-content truncate">
                                                {{ $content['created_at'] }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if(!empty($recent_users))
                    <div class="fi-table">
                        <table class="fi-ta-table">
                            <thead class="fi-ta-header">
                                <tr class="fi-ta-row">
                                    <th colspan="3" class="fi-ta-header-cell text-center">Recent Users</th>
                                </tr>
                                <tr class="fi-ta-row">
                                    <th class="fi-ta-header-cell">Id</th>
                                    <th class="fi-ta-header-cell">Name</th>
                                    <th class="fi-ta-header-cell">Created At</th>
                                </tr>
                            </thead>
                            <tbody class="fi-ta-body">
                                @foreach($recent_users as $user)
                                    <tr class="fi-ta-row">
                                        <td class="fi-ta-cell">
                                            <div class="fi-ta-cell-content truncate text-right">
                                                {{ $user['id'] }}
                                            </div>
                                        </td>
                                        <td class="fi-ta-cell">
                                            <div class="fi-ta-cell-content truncate">
                                                {{ $user['name'] }}
                                            </div>
                                        </td>
                                        <td class="fi-ta-cell">
                                            <div class="fi-ta-cell-content truncate">
                                                {{ $user['created_at'] }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            {{-- </div> --}}

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

