<?php

declare(strict_types=1);

use Cron\CronExpression as CoreCronExpression;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Casts\CronExpression;

it('get returns null when value is null', function (): void {
    $cast = new CronExpression();
    $model = Mockery::mock(Model::class);

    $result = $cast->get($model, 'schedule', null, []);

    expect($result)->toBeNull();
});

it('get returns CronExpression when value is a string', function (): void {
    $cast = new CronExpression();
    $model = Mockery::mock(Model::class);

    $result = $cast->get($model, 'schedule', '*/5 * * * *', []);

    expect($result)->toBeInstanceOf(CoreCronExpression::class)
        ->and($result->getExpression())->toBe('*/5 * * * *');
});

it('set returns null when value is null', function (): void {
    $cast = new CronExpression();
    $model = Mockery::mock(Model::class);

    $result = $cast->set($model, 'schedule', null, []);

    expect($result)->toBeNull();
});

it('set returns string when value is a string', function (): void {
    $cast = new CronExpression();
    $model = Mockery::mock(Model::class);

    $result = $cast->set($model, 'schedule', '*/5 * * * *', []);

    expect($result)->toBe('*/5 * * * *');
});

it('set returns expression string when value is a CronExpression', function (): void {
    $cast = new CronExpression();
    $model = Mockery::mock(Model::class);
    $cron = new CoreCronExpression('0 12 * * *');

    $result = $cast->set($model, 'schedule', $cron, []);

    expect($result)->toBe('0 12 * * *');
});
