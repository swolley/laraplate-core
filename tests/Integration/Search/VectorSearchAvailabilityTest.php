<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Modules\Core\Search\Services\VectorSearchAvailability;
use Modules\Core\Tests\Stubs\Search\VectorGuardEngineStub;
use Modules\Core\Tests\Stubs\Search\VectorGuardPlainEngineStub;
use Modules\Core\Tests\Stubs\Search\VectorGuardSecondStubModel;
use Modules\Core\Tests\Stubs\Search\VectorGuardStubModel;

beforeEach(function (): void {
    Cache::flush();
    config()->set('core.search.vector.enabled', true);
    config()->set('core.search.vector.suspended_reason', null);
    config()->set('core.search.vector.dimensions', 384);
    VectorGuardStubModel::$engine = new VectorGuardEngineStub(384);
    $this->guard = new VectorSearchAvailability();
});

afterEach(function (): void {
    VectorGuardStubModel::$engine = null;
});

it('is available when enabled, not suspended and dimensions match', function (): void {
    $result = $this->guard->check(new VectorGuardStubModel());

    expect($result->available)->toBeTrue()->and($result->reason)->toBeNull();
});

it('reports disabled when vector search is disabled', function (): void {
    config()->set('core.search.vector.enabled', false);

    $result = $this->guard->check(new VectorGuardStubModel());

    expect($result->available)->toBeFalse()->and($result->reason)->toBe('disabled');
});

it('reports suspended when a suspended reason is set', function (): void {
    config()->set('core.search.vector.suspended_reason', 'model switch in progress');

    $result = $this->guard->check(new VectorGuardStubModel());

    expect($result->available)->toBeFalse()->and($result->reason)->toBe('suspended');
});

it('reports dimension_mismatch when the index dimensions differ from the setting', function (): void {
    VectorGuardStubModel::$engine = new VectorGuardEngineStub(768);

    $result = $this->guard->check(new VectorGuardStubModel());

    expect($result->available)->toBeFalse()->and($result->reason)->toBe('dimension_mismatch');
});

it('does not treat an index without vectors as a mismatch', function (): void {
    VectorGuardStubModel::$engine = new VectorGuardEngineStub(null);

    expect($this->guard->check(new VectorGuardStubModel())->available)->toBeTrue();
});

it('skips the dimension check for an engine that does not report dimensions', function (): void {
    VectorGuardStubModel::$engine = new VectorGuardPlainEngineStub();

    expect($this->guard->check(new VectorGuardStubModel())->available)->toBeTrue();
});

it('answers disabled before suspended before dimension_mismatch', function (): void {
    VectorGuardStubModel::$engine = new VectorGuardEngineStub(768);
    config()->set('core.search.vector.suspended_reason', 'x');
    config()->set('core.search.vector.enabled', false);

    expect($this->guard->check(new VectorGuardStubModel())->reason)->toBe('disabled');

    config()->set('core.search.vector.enabled', true);

    expect($this->guard->check(new VectorGuardStubModel())->reason)->toBe('suspended');

    config()->set('core.search.vector.suspended_reason', '');

    expect($this->guard->check(new VectorGuardStubModel())->reason)->toBe('dimension_mismatch');
});

it('asks the engine once within the cache window', function (): void {
    $engine = new VectorGuardEngineStub(384);
    VectorGuardStubModel::$engine = $engine;

    $this->guard->check(new VectorGuardStubModel());
    $this->guard->check(new VectorGuardStubModel());

    expect($engine->calls)->toBe(1);
});

it('asks the engine again after forget()', function (): void {
    $engine = new VectorGuardEngineStub(384);
    VectorGuardStubModel::$engine = $engine;

    $this->guard->check(new VectorGuardStubModel());
    $this->guard->forget();
    $engine->dimensions = 768;
    $result = $this->guard->check(new VectorGuardStubModel());

    expect($engine->calls)->toBe(2)->and($result->reason)->toBe('dimension_mismatch');
});

it('asks the engine again for every model class after forget()', function (): void {
    $engine = new VectorGuardEngineStub(384);
    VectorGuardStubModel::$engine = $engine;

    $this->guard->check(new VectorGuardStubModel());
    $this->guard->check(new VectorGuardSecondStubModel());
    expect($engine->calls)->toBe(2);

    $this->guard->forget();
    $this->guard->check(new VectorGuardStubModel());
    $this->guard->check(new VectorGuardSecondStubModel());

    expect($engine->calls)->toBe(4);
});

it('keeps the key registry alive as long as a dimension key is cached', function (): void {
    $engine = new VectorGuardEngineStub(384);
    VectorGuardStubModel::$engine = $engine;

    $this->guard->check(new VectorGuardStubModel());
    $this->travel(3590)->seconds();
    $this->guard->check(new VectorGuardStubModel());
    $this->travel(40)->seconds();
    expect($engine->calls)->toBe(2);

    $this->guard->forget();
    $this->guard->check(new VectorGuardStubModel());

    expect($engine->calls)->toBe(3);
});
