<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Contracts\IDynamicEntityTypable;
use Modules\Core\Helpers\HasDynamicContents;

it('stores and reads dynamic fields from components for models using HasDynamicContents', function (): void {
    $presettable = new class
    {
        public function getFieldsFromSnapshot(): \Illuminate\Database\Eloquent\Collection
        {
            return new \Illuminate\Database\Eloquent\Collection([(object) ['name' => 'public_email']]);
        }
    };

    $model = new class extends Model
    {
        use HasDynamicContents;

        protected $table = 'fake_dynamic_contents';

        protected $guarded = [];

        protected function getComponentsAttribute(): array
        {
            return json_decode((string) ($this->attributes['components'] ?? '{}'), true) ?? [];
        }

        protected function setComponentsAttribute(array $components): void
        {
            $current = $this->getComponentsAttribute();
            $this->attributes['components'] = json_encode(array_merge($current, $components));
        }

        public static function getEntityType(): IDynamicEntityTypable
        {
            return new class implements IDynamicEntityTypable
            {
                public static function values(): array
                {
                    return ['fake_dynamic_contents'];
                }

                public static function isValid(string $value): bool
                {
                    return in_array($value, self::values(), true);
                }

                public static function validationRule(): string
                {
                    return 'in:fake_dynamic_contents';
                }

                public static function tryFrom(string $value): ?static
                {
                    if (! self::isValid($value)) {
                        return null;
                    }

                    return new static;
                }

                public function toScalar(): string
                {
                    return 'fake_dynamic_contents';
                }
            };
        }

        public static function getEntityModelClass(): string
        {
            return Model::class;
        }
    };

    $model->setRelation('presettable', $presettable);
    $model->presettable_id = 10;
    $model->public_email = 'editor@example.com';

    $raw_components = json_decode((string) $model->getAttributes()['components'], true);

    expect($raw_components)->toBeArray()
        ->and($raw_components)->toHaveKey('public_email')
        ->and($raw_components['public_email'])->toBe('editor@example.com')
        ->and($model->public_email)->toBe('editor@example.com');
});
