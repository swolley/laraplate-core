<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Support\BooleanInput;
use Modules\Core\Tests\Fixtures\FakeArticle;

it('coerces the recognised boolean forms', function (mixed $input, bool $expected): void {
    expect(BooleanInput::coerce($input))->toBe($expected);
})->with([
    'true bool' => [true, true],
    'false bool' => [false, false],
    'int 1' => [1, true],
    'int 0' => [0, false],
    'string true' => ['true', true],
    'string FALSE' => ['FALSE', false],
    'string yes' => ['yes', true],
    'string no' => ['no', false],
    'string 1' => ['1', true],
    'string 0' => ['0', false],
    'string on' => ['on', true],
    'string off' => ['off', false],
]);

it('leaves non-boolean strings unchanged so validation can reject them', function (): void {
    expect(BooleanInput::coerce('maybe'))->toBe('maybe')
        ->and(BooleanInput::coerce('nope'))->toBe('nope')
        ->and(BooleanInput::coerce(2))->toBe(2);
});

it('lists boolean attributes from casts without touching the database', function (): void {
    $article = new FakeArticle;

    expect(BooleanInput::attributeNames($article))->toContain('is_published')
        ->and(BooleanInput::attributeNames($article))->not->toContain('body')
        ->and(BooleanInput::isBooleanAttribute($article, 'articles.is_published'))->toBeTrue()
        ->and(BooleanInput::isBooleanAttribute($article, 'slug'))->toBeFalse();
});

it('also discovers boolean attributes declared only in validation rules', function (): void {
    $model = new class extends Model
    {
        public $exists = false;

        protected $table = 'boolean_rule_only';

        public function getOperationRules(?string $operation = null): array
        {
            return [
                'flagged' => ['sometimes', 'boolean'],
                'title' => ['string'],
            ];
        }
    };

    expect(BooleanInput::attributeNames($model))->toBe(['flagged']);
});
