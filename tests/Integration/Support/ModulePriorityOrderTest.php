<?php

declare(strict_types=1);

use Nwidart\Modules\Facades\Module;

/**
 * Lower priority number boots first, so every required module must have a strictly lower number than its dependent.
 * Module repository keys are lowercase module names.
 *
 * @return array{checked: int, violations: list<string>}
 */
function module_priority_report(): array
{
    $enabled = Module::allEnabled();
    $order = array_flip(array_keys(Module::getOrdered()));
    $checked = 0;
    $violations = [];

    foreach ($enabled as $module) {
        foreach ((array) ($module->get('requires') ?? []) as $dependency) {
            $key = mb_strtolower((string) $dependency);

            if (! isset($enabled[$key])) {
                continue;
            }

            $checked++;
            $priority = (int) $module->get('priority');
            $dependency_priority = (int) $enabled[$key]->get('priority');

            if ($dependency_priority >= $priority) {
                $violations[] = "{$module->getName()} ({$priority}) requires {$dependency} ({$dependency_priority})";
            }

            if ($order[$key] > $order[mb_strtolower($module->getName())]) {
                $violations[] = "{$module->getName()} boots before {$dependency}";
            }
        }
    }

    return ['checked' => $checked, 'violations' => $violations];
}

it('verifies at least one module dependency', function (): void {
    expect(module_priority_report()['checked'])->toBeGreaterThan(0);
});

it('gives every required module a strictly lower priority number and an earlier boot position', function (): void {
    expect(module_priority_report()['violations'])->toBe([]);
});
