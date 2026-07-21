<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Models\Concerns\HasVersions;
use Overtrue\LaravelVersionable\VersionStrategy;
use ReflectionClass;

final class ForcedVersionStrategySettings
{
    /** @var list<string>|null */
    private ?array $names = null;

    /** @return list<string> */
    public function names(): array
    {
        if ($this->names !== null) {
            return $this->names;
        }

        $names = [];
        foreach (models(onlyActive: false) as $model_class) {
            if (! is_subclass_of($model_class, Model::class) || ! in_array(HasVersions::class, class_uses_recursive($model_class), true)) {
                continue;
            }
            $reflection = new ReflectionClass($model_class);
            if (! $reflection->hasProperty('versionStrategy')) {
                continue;
            }
            $property = $reflection->getProperty('versionStrategy');
            if ($property->getDeclaringClass()->getName() !== $model_class
                || ! $property->hasDefaultValue()
                || $property->getDefaultValue() !== VersionStrategy::DIFF) {
                continue;
            }
            /** @var Model $model */
            $model = $reflection->newInstanceWithoutConstructor();
            $names[] = 'version_strategy_'.$model->getTable();
        }

        sort($names);

        return $this->names = array_values(array_unique($names));
    }

    public function isForced(string $setting_name): bool
    {
        return in_array($setting_name, $this->names(), true);
    }
}
