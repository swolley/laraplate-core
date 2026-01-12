<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-clock" class="w-5 h-5 inline mr-2" />
                Recent Activity
            </div>
        </x-slot>

        @isset($error)
            <div class="fi-alert fi-color-danger fi-size-sm">
                <div class="fi-alert-icon">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                </div>
                <div class="fi-alert-content">
                    <strong>Error:</strong> {{ $error }}
                </div>
            </div>
        @else
            <div class="fi-section-content">
                @if(!empty($recent_contents))
                    <div class="fi-ta mb-3">
                        <div class="fi-ta-ctn fi-ta-ctn-with-header">
                            <div class="fi-ta-main">
                                <div class="fi-ta-content-ctn">
                                    <table class="fi-ta-table">
                                        <thead>
                                            <tr>
                                                <th colspan="4" class="fi-ta-header-cell text-center">Recent Contents</th>
                                            </tr>
                                            <tr>
                                                <th class="fi-ta-header-cell text-right">Id</th>
                                                <th class="fi-ta-header-cell w-full">Title</th>
                                                <th class="fi-ta-header-cell w-full">Locale</th>
                                                <th class="fi-ta-header-cell text-right">Last Updated</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($recent_contents as $content)
                                            <tr class="fi-ta-row">
                                                <td class="fi-ta-cell">
                                                    <div class="fi-ta-cell">
                                                        <div class="fi-ta-col">
                                                            <div class="fi-ta-text-item fi-ta-text text-right">{{ $content['id'] }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="fi-ta-cell">
                                                    <div class="fi-ta-cell">
                                                        <div class="fi-ta-col">
                                                            <div class="fi-ta-text-item fi-ta-text">{{ $content['title']['title'] }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="fi-ta-cell">
                                                    <div class="fi-ta-cell">
                                                        <div class="fi-ta-col">
                                                            <div class="fi-ta-text-item fi-ta-text">{{ $content['locale'] }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="fi-ta-cell">
                                                    <div class="fi-ta-cell">
                                                        <div class="fi-ta-col">
                                                            <div class="fi-ta-text-item fi-ta-text text-right">{{ $content['updated_at'] }}</div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(!empty($recent_users))
                    <div class="fi-ta">
                        <div class="fi-ta-ctn fi-ta-ctn-with-header">
                            <div class="fi-ta-main">
                                <div class="fi-ta-content-ctn">
                                    <table class="fi-ta-table">
                                        <thead>
                                            <tr>
                                                <th colspan="3" class="fi-ta-header-cell text-center">Recent Users</th>
                                            </tr>
                                            <tr>
                                                <th class="fi-ta-header-cell text-right">Id</th>
                                                <th class="fi-ta-header-cell w-full">Email</th>
                                                <th class="fi-ta-header-cell text-right">Last Login</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($recent_users as $user)
                                                <tr class="fi-ta-row">
                                                    <td class="fi-ta-cell">
                                                        <div class="fi-ta-cell">
                                                            <div class="fi-ta-col">
                                                                <div class="fi-ta-text-item fi-ta-text text-right">{{ $user['id'] }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="fi-ta-cell">
                                                        <div class="fi-ta-cell">
                                                            <div class="fi-ta-col">
                                                                <div class="fi-ta-text-item fi-ta-text">{{ $user['email'] }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="fi-ta-cell">
                                                        <div class="fi-ta-cell">
                                                            <div class="fi-ta-col">
                                                                <div class="fi-ta-text-item fi-ta-text text-right">{{ $user['last_login_at'] }}</div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(empty($recent_contents) && empty($recent_users))
                    <div class="fi-alert fi-color-gray fi-size-sm">
                        <div class="fi-alert-content">
                            No recent activity found.
                        </div>
                    </div>
                @endif
            </div>
        @endisset
    </x-filament::section>
</x-filament-widgets::widget>

