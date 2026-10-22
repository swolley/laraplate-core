<?php

declare(strict_types=1);

namespace Modules\Core\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Database\Seeders\CoreDatabaseSeeder;
use Modules\Core\Enums\VersionStrategy;
use Modules\Core\Models\Concerns\HasVersions;
use Modules\Core\Versioning\NeverVersionedModels;
use ReflectionClass;

final class ForcedVersionStrategySettings
{
    /**
     * @var list<string>|null
     */
    private ?array $names = null;

    /**
     * @return list<string>
     */
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

            // Never versioned (D8, D10): a row left from an older seed is hidden and refused like a forced one.
            if (NeverVersionedModels::includes($model_class) && ! $reflection->isAbstract()) {
                /** @var Model $model */
                $model = $reflection->newInstanceWithoutConstructor();
                $names[] = PerModelSettingResolver::nameFor(CoreDatabaseSeeder::VERSIONING_NAME_PREFIX, $model->getTable());

                continue;
            }

            if (! $reflection->hasProperty('versionStrategy')) {
                continue;
            }
            $property = $reflection->getProperty('versionStrategy');

            if ($model_class !== $property->getDeclaringClass()->getName()
                || ! $property->hasDefaultValue()
                || $property->getDefaultValue() !== VersionStrategy::DIFF) {
                continue;
            }

            /** @var Model $model */
            $model = $reflection->newInstanceWithoutConstructor();
            $names[] = PerModelSettingResolver::nameFor(CoreDatabaseSeeder::VERSIONING_NAME_PREFIX, $model->getTable());
        }

        sort($names);

        return $this->names = array_values(array_unique($names));
    }

    public function isForced(string $setting_name): bool
    {
        return in_array($setting_name, $this->names(), true);
    }
}
