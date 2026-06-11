<?php

declare(strict_types=1);


it('resolves app swagger docs in the main resources directory', function (): void {
    expect(swagger_doc_path('App'))->toBe(resource_path('swagger/App-swagger.json'));
});

it('resolves module swagger docs under module resources directory', function (): void {
    expect(swagger_doc_path('Core'))->toBe(module_path('Core', 'resources/swagger/Core-swagger.json'));
});
