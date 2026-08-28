<?php

declare(strict_types=1);

use Modules\Core\Http\Requests\MediaUploadRequest;

it('exposes upload rules without a resolved owner model for swagger generation', function (): void {
    $request = new MediaUploadRequest();

    $rules = $request->rules();

    expect($rules)
        ->toHaveKey('file')
        ->toHaveKey('collection')
        ->and($rules['collection'])->toContain('required', 'string');
});
