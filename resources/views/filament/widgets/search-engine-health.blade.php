<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Search Engine
        </x-slot>

        @if($error)
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
                <div class="fi-table">
                    <table class="fi-ta-table">
                        <thead class="fi-ta-header">
                            <tr class="fi-ta-row">
                                <th colspan="5" class="fi-ta-header-cell text-center">{{ ucfirst($driver ?? 'Unknown') }}</th>
                            </tr>
                            <tr class="fi-ta-row">
                                <th class="fi-ta-header-cell">Model</th>
                                <th class="fi-ta-header-cell">Index</th>
                                <th class="fi-ta-header-cell">Status</th>
                                <th class="fi-ta-header-cell">Records</th>
                                <th class="fi-ta-header-cell">Documents</th>
                            </tr>
                        </thead>
                        <tbody class="fi-ta-body">
                            @foreach($models as $model)
                                <tr class="fi-ta-row">
                                    <td class="fi-ta-cell">
                                        <div class="fi-ta-cell-content">
                                            {{ $model['name'] }}
                                        </div>
                                    </td>
                                    <td class="fi-ta-cell">
                                        <div class="fi-ta-cell-content">
                                            <code class="text-xs">{{ $model['searchable_as'] }}</code>
                                        </div>
                                    </td>
                                    <td class="fi-ta-cell">
                                        <div class="fi-ta-cell-content text-center">
                                            @if($model['index_exists'])
                                                <x-filament::badge color="success" size="sm">Active</x-filament::badge>
                                            @else
                                                <x-filament::badge color="warning" size="sm">Missing</x-filament::badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="fi-ta-cell">
                                        <div class="fi-ta-cell-content text-right">
                                            {{ number_format($model['count']) }}
                                        </div>
                                    </td>
                                    <td class="fi-ta-cell">
                                        <div class="fi-ta-cell-content text-right">
                                            {{ number_format($model['documents']) }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            @if(empty($models))
                                <tr class="fi-ta-row">
                                    <td colspan="5" class="fi-ta-cell">
                                        <div class="fi-ta-cell-content">
                                            No searchable models found.
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
