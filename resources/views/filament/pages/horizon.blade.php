<style>
    .fi-main .fi-page {
        height: 100%;
    }
    .fi-main .fi-page .fi-page-header-main-ctn {
        height: 100%;
    }
    .fi-main .fi-page .fi-page-header-main-ctn .fi-page-main {
        height: 100%;
    }
    .fi-main .fi-page .fi-page-header-main-ctn .fi-page-main .fi-page-content {
        height: 100%;
    }
</style>

<x-filament-panels::page>
    @if(config('horizon.path'))
        <iframe 
            src="{{ url(config('horizon.path')) }}"
            style="width: 100%; height: 100%; border: 0;"
            title="Laravel Horizon" 
        ></iframe>
    @else
        <div class="p-4 text-center text-gray-500">
            Horizon is not configured
        </div>
    @endif
</x-filament-panels::page>