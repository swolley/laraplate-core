<?php

declare(strict_types=1);

use Modules\Core\Models\Setting;

it('flags only a scalar value missing from a non-empty choice list', function (mixed $value, ?array $choices, bool $expected): void {
    $setting = new Setting;
    $setting->setRawAttributes([
        'value' => json_encode($value),
        'choices' => $choices === null ? null : json_encode($choices),
    ]);

    expect($setting->isValueOutsideChoices())->toBe($expected);
})->with([
    'offered' => ['ollama:llama3.2:3b', ['ollama:llama3.2:3b', 'openai:gpt-4o'], false],
    'no longer offered' => ['openai:gpt-3.5', ['openai:gpt-4o'], true],
    'no choices' => ['free text', null, false],
    'empty choices' => ['free text', [], false],
    'numeric against a numeric choice' => [5, [5, 10], false],
    'list value of a checkbox setting' => [['mail', 'sms'], ['mail', 'database'], false],
]);
