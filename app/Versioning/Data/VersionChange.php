<?php

declare(strict_types=1);

namespace Modules\Core\Versioning\Data;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Enums\VersionChangeType;
use Overtrue\LaravelVersionable\VersionStrategy;

final readonly class VersionChange
{
    /**
     * @param  array<string, mixed>  $originalContents
     * @param  array<string, mixed>  $contents
     * @param  list<string>  $encryptedAttributes
     * @param  array<string, mixed>|null  $subjectKey
     */
    public function __construct(
        public Model $model,
        public VersionChangeType $type,
        public array $originalContents,
        public array $contents,
        public VersionStrategy $strategy,
        public string|DateTimeInterface|null $time = null,
        public ?int $userId = null,
        public array $encryptedAttributes = [],
        public ?string $relationPath = null,
        public ?array $subjectKey = null,
    ) {}

    /**
     * Build the payload used by legacy explicit entry points.
     *
     * Lifecycle callbacks pass pre-query images directly to the constructor.
     *
     * @param  array<string, mixed>  $replacements
     * @param  list<string>  $encryptedAttributes
     */
    public static function forModel(
        Model $model,
        array $replacements = [],
        string|DateTimeInterface|null $time = null,
        ?VersionStrategy $strategy = null,
        ?int $userId = null,
        array $encryptedAttributes = [],
        VersionChangeType $type = VersionChangeType::Updated,
    ): self {
        $resolved_strategy = $strategy ?? self::resolveStrategy($model);
        $contents = method_exists($model, 'getVersionableAttributes')
            ? $model->getVersionableAttributes($resolved_strategy, $replacements)
            : array_replace($model->getAttributes(), $replacements);
        $original_contents = method_exists($model, 'getOriginalVersionableAttributes')
            ? $model->getOriginalVersionableAttributes($resolved_strategy)
            : [];

        if ($type === VersionChangeType::Created) {
            $original_contents = [];
        }

        if ($type === VersionChangeType::Deleted) {
            $contents = [];
        }

        return new self(
            model: $model,
            type: $type,
            originalContents: self::encryptSelected($original_contents, $encryptedAttributes),
            contents: self::encryptSelected($contents, $encryptedAttributes),
            strategy: $resolved_strategy,
            time: $time,
            userId: $userId ?? self::resolveUserId($model),
            encryptedAttributes: $encryptedAttributes,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $attributes
     * @return array<string, mixed>
     */
    public static function encryptSelected(array $payload, array $attributes): array
    {
        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $payload)) {
                $payload[$attribute] = encrypt($payload[$attribute]);
            }
        }

        return $payload;
    }

    private static function resolveStrategy(Model $model): VersionStrategy
    {
        if (! method_exists($model, 'getVersionStrategy')) {
            return VersionStrategy::DIFF;
        }

        $strategy = $model->getVersionStrategy();

        return $strategy instanceof VersionStrategy ? $strategy : VersionStrategy::DIFF;
    }

    private static function resolveUserId(Model $model): ?int
    {
        if (! method_exists($model, 'getVersionUserId')) {
            return null;
        }

        $user_id = $model->getVersionUserId();

        return is_numeric($user_id) ? (int) $user_id : null;
    }
}
