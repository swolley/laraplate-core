<?php

declare(strict_types=1);

use Illuminate\Config\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Modules\Core\Casts\SettingTypeEnum;
use Modules\Core\Models\Setting;
use Modules\Core\Services\DatabaseConfigOverlay;
use Modules\Core\Services\PerModelSettingResolver;

it('overlays settings under the namespace of the module that declares them', function (): void {
    $config = new Repository([
        'ai' => [
            'features' => [
                'faq' => [
                    'enabled' => true,
                ],
            ],
        ],
    ]);

    $overlay = new DatabaseConfigOverlay($config);

    $overlay->applySettings(new Collection([
        (object) ['name' => 'features.faq.enabled', 'module' => 'AI', 'value' => false],
        (object) ['name' => 'soft_deletes.enabled.core_users', 'module' => 'Core', 'value' => true],
    ]));

    expect($config->get('ai.features.faq.enabled'))->toBeFalse()
        ->and($config->get('core.soft_deletes.enabled.core_users'))->toBeTrue()
        ->and($config->has('features.faq.enabled'))->toBeFalse();
});

it('applies a single setting model onto runtime config', function (): void {
    $config = new Repository([
        'core' => [
            'crud.expose_api' => true,
        ],
    ]);

    $overlay = new DatabaseConfigOverlay($config);

    $setting = new Setting([
        'name' => 'crud.expose_api',
        'module' => 'Core',
        'value' => false,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'core',
    ]);

    $overlay->applySetting($setting);

    expect($config->get('core.crud.expose_api'))->toBeFalse();
});

it('does not apply settings that no module declares', function (): void {
    $config = new Repository([]);

    $overlay = new DatabaseConfigOverlay($config);

    $setting = new Setting([
        'name' => 'default_language',
        'value' => 'it',
        'type' => SettingTypeEnum::String,
        'group_name' => 'base',
    ]);

    $overlay->applySetting($setting);

    expect($config->all())->toBe([]);
});

it('builds the config key from the module and the setting name', function (): void {
    expect(DatabaseConfigOverlay::configKey('Core', 'auth.registration.enabled'))->toBe('core.auth.registration.enabled')
        ->and(DatabaseConfigOverlay::configKey('Billing', 'invoices.auto_post'))->toBe('billing.invoices.auto_post')
        ->and(DatabaseConfigOverlay::configKey(null, 'default_language'))->toBeNull()
        ->and(DatabaseConfigOverlay::configKey('', 'default_language'))->toBeNull()
        ->and(DatabaseConfigOverlay::configKey('Core', ''))->toBeNull();
});

it('overlays settings for modules not hardcoded in core', function (): void {
    $config = new Repository([]);

    $overlay = new DatabaseConfigOverlay($config);

    $overlay->applySettings(new Collection([
        (object) ['name' => 'invoices.auto_post', 'module' => 'Billing', 'value' => true],
    ]));

    expect($config->get('billing.invoices.auto_post'))->toBeTrue();
});

it('swallows database errors while applying overlay from database', function (): void {
    $config = new Repository([]);
    $resolver = Model::getConnectionResolver();
    $overlay = new DatabaseConfigOverlay($config);

    Model::setConnectionResolver(new class($resolver) implements ConnectionResolverInterface
    {
        public function __construct(private readonly ConnectionResolverInterface $resolver) {}

        public function connection($name = null): ConnectionInterface
        {
            throw new RuntimeException('connection unavailable');
        }

        public function getDefaultConnection(): string
        {
            return $this->resolver->getDefaultConnection();
        }

        public function setDefaultConnection($name): void
        {
            $this->resolver->setDefaultConnection($name);
        }
    });

    try {
        $overlay->applyFromDatabase(Mockery::mock(PerModelSettingResolver::class));
    } finally {
        Model::setConnectionResolver($resolver);
    }

    expect($config->all())->toBe([]);
});
