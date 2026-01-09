<x-filament-panels::page>
    <div class="fi-page-content">
        <iframe 
            src="{{ $this->getSwaggerUrl() }}" 
            class="w-full h-[calc(100vh-12rem)] border-0 rounded-lg"
            title="Swagger API Documentation"
        ></iframe>
    </div>
</x-filament-panels::page>

