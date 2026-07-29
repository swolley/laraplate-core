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

uses(Tests\TestCase::class);

it('overlays dot-named settings onto runtime config', function (): void {
    $config = new Repository([
        'ai' => [
            'features' => [
                'chat' => [
                    'enabled' => true,
                ],
            ],
        ],
    ]);

    $overlay = new DatabaseConfigOverlay($config);

    $overlay->applySettings(new Collection([
        (object) ['name' => 'ai.features.chat.enabled', 'value' => false],
        (object) ['name' => 'soft_deletes_core_users', 'value' => true],
    ]));

    expect($config->get('ai.features.chat.enabled'))->toBeFalse()
        ->and($config->has('soft_deletes_core_users'))->toBeFalse();
});

it('applies a single setting model onto runtime config', function (): void {
    $config = new Repository([
        'core' => [
            'expose_crud_api' => true,
        ],
    ]);

    $overlay = new DatabaseConfigOverlay($config);

    $setting = new Setting([
        'name' => 'core.expose_crud_api',
        'value' => false,
        'type' => SettingTypeEnum::Boolean,
        'group_name' => 'core',
    ]);

    $overlay->applySetting($setting);

    expect($config->get('core.expose_crud_api'))->toBeFalse();
});

it('does not apply non-overlay setting names onto runtime config', function (): void {
    $config = new Repository([]);

    $overlay = new DatabaseConfigOverlay($config);

    $setting = new Setting([
        'name' => 'default_language',
        'value' => 'it',
        'type' => SettingTypeEnum::String,
        'group_name' => 'base',
    ]);

    $overlay->applySetting($setting);

    expect($config->has('default_language'))->toBeFalse();
});

it('treats any dot-notation setting name as a config overlay candidate', function (): void {
    expect(DatabaseConfigOverlay::shouldOverlay('core.enable_user_registration'))->toBeTrue()
        ->and(DatabaseConfigOverlay::shouldOverlay('future_module.feature.enabled'))->toBeTrue()
        ->and(DatabaseConfigOverlay::shouldOverlay('version_strategy_core_users'))->toBeFalse()
        ->and(DatabaseConfigOverlay::shouldOverlay('default_language'))->toBeFalse()
        ->and(DatabaseConfigOverlay::shouldOverlay(''))->toBeFalse();
});

it('overlays settings for modules not hardcoded in core', function (): void {
    $config = new Repository([]);

    $overlay = new DatabaseConfigOverlay($config);

    $overlay->applySettings(new Collection([
        (object) ['name' => 'billing.invoices.auto_post', 'value' => true],
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
