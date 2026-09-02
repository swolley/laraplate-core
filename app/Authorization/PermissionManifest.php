<?php

declare(strict_types=1);

namespace Modules\Core\Authorization;

use function modules;

use Illuminate\Database\Eloquent\Model;
use LogicException;
use Modules\Core\Authorization\Contracts\DeclaresPermissions;
use Modules\Core\Support\PermissionName;

/**
 * The domain permissions and CRUD exclusions declared by the enabled modules.
 *
 * Modules do not register into this: they declare, and the manifest discovers
 * them the way {@see \Modules\Core\Seeding\SeedGraphBuilder} discovers seeders.
 * Nothing in a request reads these names — the policies build the name they need
 * on the spot from {@see PermissionName} — so the manifest is bound lazily and
 * stays unbuilt for the whole HTTP lifecycle. Its one caller is
 * `permission:refresh`.
 */
final class PermissionManifest
{
    /**
     * @var array<string, class-string<DeclaresPermissions>>|null
     */
    private ?array $declarations = null;

    /**
     * Every declared domain permission name, module order preserved.
     *
     * @return list<string>
     */
    public function names(): array
    {
        $names = [];

        foreach (array_keys($this->declarations()) as $module) {
            foreach ($this->namesFor($module) as $name) {
                $names[] = $name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * One module's declared domain permission names.
     *
     * Lets a module seeder materialize its own slice without running a full
     * `permission:refresh`, which is what the module test suites rely on.
     *
     * @return list<string>
     */
    public function namesFor(string $module): array
    {
        $declaration = $this->declarations()[$module] ?? null;

        if ($declaration === null) {
            return [];
        }

        $names = [];

        foreach ($declaration::operations() as $model_class => $operations) {
            foreach ($operations as $operation) {
                $names[] = PermissionName::forClass($model_class, $operation);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Models the enabled modules keep out of CRUD permission generation.
     *
     * @return list<class-string<Model>>
     */
    public function excludedModels(): array
    {
        $excluded = [];

        foreach ($this->declarations() as $declaration) {
            foreach ($declaration::excludedModels() as $model_class) {
                $excluded[] = $model_class;
            }
        }

        return array_values(array_unique($excluded));
    }

    /**
     * Declared operations per model, across every enabled module. Used by the
     * coherence test that pairs declarations with the domain action registry.
     *
     * @return array<class-string<Model>, list<string>>
     */
    public function operations(): array
    {
        $operations = [];

        foreach ($this->declarations() as $declaration) {
            foreach ($declaration::operations() as $model_class => $model_operations) {
                $operations[$model_class] = array_values(array_unique([
                    ...$operations[$model_class] ?? [],
                    ...$model_operations,
                ]));
            }
        }

        return $operations;
    }

    /**
     * One `class_exists()` per enabled module, memoized for the process.
     *
     * @return array<string, class-string<DeclaresPermissions>>
     */
    private function declarations(): array
    {
        if ($this->declarations !== null) {
            return $this->declarations;
        }

        $declarations = [];

        foreach (modules(prioritySort: false) as $module) {
            $class = sprintf('Modules\\%s\\Authorization\\%sPermissions', $module, $module);

            if (! class_exists($class)) {
                continue;
            }

            // A class sitting at the conventional path without the contract is a
            // typo away from silently declaring nothing, which would show up much
            // later as a missing permission.
            throw_unless(
                is_a($class, DeclaresPermissions::class, true),
                LogicException::class,
                sprintf('[%s] must implement [%s] to declare module permissions.', $class, DeclaresPermissions::class),
            );

            $declarations[$module] = $class;
        }

        return $this->declarations = $declarations;
    }
}
