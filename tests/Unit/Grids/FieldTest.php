<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Grids\Components\Field;
use Modules\Core\Grids\Components\Funnel;
use Modules\Core\Grids\Components\Option;
use Modules\Core\Grids\Definitions\FieldType;
use Modules\Core\Grids\Traits\HasGridUtils;
use Modules\Core\Tests\Stubs\Grids\FieldModelStub;
use Modules\Core\Tests\Stubs\Grids\FieldModelWithoutAppendAccessorStub;


it('builds full alias and full query alias', function (): void {
    $field = new Field('users', 'email', 'mail', FieldType::COLUMN, new FieldModelStub());

    expect($field->getAlias())->toBe('mail')
        ->and($field->getFullAlias())->toBe('users.mail')
        ->and($field->getFullQueryAlias())->toBe('users.mail');
});

it('returns null aliases for root path fields', function (): void {
    $field = new Field('', 'name', null, FieldType::COLUMN, new FieldModelStub());

    expect($field->getFullAlias())->toBeNull()
        ->and($field->getFullQueryAlias())->toBeNull();
});

it('parses validation rules and serializes field metadata', function (): void {
    $field = new Field('users', 'email', null, FieldType::COLUMN, new FieldModelStub());
    $array = $field->toArray();

    expect($field->getRules())->toContain('required')
        ->and($field->getRules())->toContain('email')
        ->and($array['required'])->toBeTrue()
        ->and($array['fieldType'])->toBe(FieldType::COLUMN->value)
        ->and($field->jsonSerialize())->toBe($array);
});

it('throws when disabling read on aggregated field', function (): void {
    $field = new Field('users', 'name', null, FieldType::COUNT, new FieldModelStub());

    expect(fn () => $field->readable(false))->toThrow(UnexpectedValueException::class);
});

it('throws when enabling write on aggregated field', function (): void {
    $field = new Field('users', 'name', null, FieldType::COUNT, new FieldModelStub());

    expect(fn () => $field->writable(true))->toThrow(UnexpectedValueException::class);
});

it('throws when append field is readable without getter', function (): void {
    $field = new Field('users', 'missing_accessor', null, FieldType::COLUMN, new FieldModelWithoutAppendAccessorStub());

    expect(fn () => $field->readable(true))->toThrow(UnexpectedValueException::class);
});

it('creates field instances through static factory', function (): void {
    $factory = Field::create('users.email', 'mail_alias', true, false);
    $field = $factory(new FieldModelStub());

    expect($field->getAlias())->toBe('mail_alias')
        ->and($field->getName())->toBe('email')
        ->and($field->getPath())->toBe('users')
        ->and($field->isReadable())->toBeTrue()
        ->and($field->isWritable())->toBeFalse();
});

it('assigns option and funnel callbacks on field', function (): void {
    $model = new FieldModelStub();
    $field = new Field('users', 'email', null, FieldType::COLUMN, $model);

    $option = new Option($model, $field, $field);
    $funnel = new Funnel($model, $field, $field);

    $field->options(static fn (): Option => $option);
    $field->funnel(static fn (): Funnel => $funnel);

    expect($field->hasOption())->toBeTrue()
        ->and($field->getOption())->toBe($option)
        ->and($field->hasFunnel())->toBeTrue()
        ->and($field->getFunnel())->toBe($funnel);
});

it('checks fillable hidden and append helpers', function (): void {
    $model = new FieldModelStub();
    $fillable = new Field('', 'name', null, FieldType::COLUMN, $model);
    $hidden = new Field('', 'password', null, FieldType::COLUMN, $model);
    $append = new Field('', 'computed_name', null, FieldType::COLUMN, $model);

    expect($fillable->isFillable())->toBeTrue()
        ->and($hidden->isHidden())->toBeTrue()
        ->and($append->isAppend())->toBeTrue();
});

it('covers model getter and field type getter', function (): void {
    $model = new FieldModelStub();
    $field = new Field('users', 'email', null, FieldType::COLUMN, $model);

    expect($field->getModel())->toBe($model)
        ->and($field->getFieldType())->toBe(FieldType::COLUMN);
});

it('keeps readability true for hidden fields and writable true for non fillable fields', function (): void {
    $model = new FieldModelStub();
    $hidden = new Field('', 'password', null, FieldType::COLUMN, $model);
    $not_fillable = new Field('', 'not_fillable', null, FieldType::COLUMN, $model);

    expect($hidden->readable(true))->toBe($hidden)
        ->and($hidden->isReadable())->toBeTrue()
        ->and($not_fillable->writable(true))->toBe($not_fillable)
        ->and($not_fillable->isWritable())->toBeTrue();
});

it('throws when append field is writable without setter', function (): void {
    $model = new class extends Model
    {
        use HasGridUtils;

        protected $table = 'users';

        protected $fillable = ['missing_accessor'];

        protected $appends = ['missing_accessor'];

        public function getRules(): array
        {
            return [];
        }

        protected function casts(): array
        {
            return [];
        }
    };
    $field = new Field('', 'missing_accessor', null, FieldType::COLUMN, $model);

    expect(fn () => $field->writable(true))->toThrow(UnexpectedValueException::class);
});

it('parses regex and nested array validations', function (): void {
    $model = new class extends Model
    {
        use HasGridUtils;

        protected $table = 'users';

        protected $fillable = ['code', 'meta'];

        public function getRules(): array
        {
            return [
                'code' => 'required|regex:/^[A-Z]+$/|max:10',
                'meta' => [['string', 12], ['nullable']],
            ];
        }

        protected function casts(): array
        {
            return [];
        }
    };

    $code = new Field('', 'code', null, FieldType::COLUMN, $model);
    $meta = new Field('', 'meta', null, FieldType::COLUMN, $model);

    expect($code->getRules())->toContain('required')
        ->and($code->getRules())->toContain('max:10')
        ->and($code->getRules())->not->toContain('regex:/^[A-Z]+$/')
        ->and($meta->getRules())->toContain('string')
        ->and($meta->getRules())->toContain('nullable');
});
