<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Search Engine Health
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
            @if($engine)
                <div class="fi-section">
                    <div class="fi-section-header">
                        <h3 class="fi-section-header-heading">
                            <x-filament::icon icon="heroicon-o-information-circle" />
                            Engine Information
                        </h3>
                    </div>
                    <div class="fi-section-content">
                        <div class="fi-field-wrp">
                            <div class="fi-field">
                                <div class="fi-field-label-wrp">
                                    <label class="fi-field-label">Name</label>
                                </div>
                                <div class="fi-field-content">
                                    <div class="fi-input-wrp">
                                        <div class="fi-input">{{ class_basename($engine) }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="fi-field">
                                <div class="fi-field-label-wrp">
                                    <label class="fi-field-label">Driver</label>
                                </div>
                                <div class="fi-field-content">
                                    <div class="fi-input-wrp">
                                        <div class="fi-input">{{ config('scout.driver') }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="fi-field">
                                <div class="fi-field-label-wrp">
                                    <label class="fi-field-label">Queue</label>
                                </div>
                                <div class="fi-field-content">
                                    <div class="fi-input-wrp">
                                        <div class="fi-input">{{ config('scout.queue') ? 'Enabled' : 'Disabled' }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if($health)
                    <div class="fi-section">
                        <div class="fi-section-header">
                            <h3 class="fi-section-header-heading">
                                <x-filament::icon icon="heroicon-o-heart" />
                                Health Status
                            </h3>
                        </div>
                        <div class="fi-section-content">
                            <div class="fi-field-wrp">
                                @foreach($health['metrics'] ?? [] as $key => $value)
                                    <div class="fi-field">
                                        <div class="fi-field-label-wrp">
                                            <label class="fi-field-label">{{ ucfirst($key) }}</label>
                                        </div>
                                        <div class="fi-field-content">
                                            <div class="fi-input-wrp">
                                                <div class="fi-input">{{ $value }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            @endif

            @if(!empty($models))
                <div class="fi-section">
                    <div class="fi-section-header">
                        <h3 class="fi-section-header-heading">
                            Searchable Models
                        </h3>
                    </div>
                    <div class="fi-section-content">
                        <div class="fi-table">
                            <table class="fi-ta-table">
                                <thead class="fi-ta-header">
                                    <tr class="fi-ta-row">
                                        <th class="fi-ta-header-cell">Model</th>
                                        <th class="fi-ta-header-cell">Index</th>
                                        <th class="fi-ta-header-cell">Index Exists</th>
                                        <th class="fi-ta-header-cell">Records</th>
                                        <th class="fi-ta-header-cell">Documents</th>
                                    </tr>
                                </thead>
                                <tbody class="fi-ta-body">
                                    @foreach($models as $model)
                                        <tr class="fi-ta-row">
                                            <td class="fi-ta-cell">
                                                <div class="fi-ta-cell-content">
                                                    {{ class_basename($model['name']) }}
                                                </div>
                                            </td>
                                            <td class="fi-ta-cell">
                                                <div class="fi-ta-cell-content">
                                                    {{ $model['searchable_as'] }}
                                                </div>
                                            </td>
                                            <td class="fi-ta-cell">
                                                <div class="fi-ta-cell-content">
                                                    @if($model['index_exists'])
                                                        <x-filament::icon icon="heroicon-o-check-circle" />
                                                    @else
                                                        <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="fi-ta-cell">
                                                <div class="fi-ta-cell-content">
                                                    {{ number_format($model['count']) }}
                                                </div>
                                            </td>
                                            <td class="fi-ta-cell">
                                                <div class="fi-ta-cell-content">
                                                    {{ number_format($model['documents']) }}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
