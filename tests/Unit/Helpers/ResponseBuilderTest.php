<?php

declare(strict_types=1);

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Core\Helpers\ResponseBuilder;
use Modules\Core\Tests\LaravelTestCase;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

uses(LaravelTestCase::class);

it('builds a basic ok response from array data', function (): void {
    config(['app.debug' => false]);

    $request = Request::create('/test', 'GET');
    $builder = new ResponseBuilder($request, Carbon::now());

    $builder->setData(['foo' => 'bar']);

    expect($builder->isOk())->toBeTrue()
        ->and($builder->isError())->toBeFalse()
        ->and($builder->isEmpty())->toBeFalse()
        ->and($builder->getStatus())->toBe(SymfonyResponse::HTTP_OK);

    $response = $builder->getResponse();
    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->getStatusCode())->toBe(SymfonyResponse::HTTP_OK);

    $payload = json_decode($response->getContent(), true);

    expect($payload['meta']['status'])->toBe(SymfonyResponse::HTTP_OK);
});

it('sets status based on exception error and includes error message', function (): void {
    config(['app.debug' => false]);

    $request = Request::create('/test', 'GET');
    $builder = new ResponseBuilder($request, Carbon::now());

    $exception = new Exception('Failure', 0);

    $builder->setData(null);
    $builder->setError($exception);

    expect($builder->isError())->toBeTrue()
        ->and($builder->getStatus())->toBe(SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);

    $response = $builder->getResponse();
    $payload = json_decode($response->getContent(), true);

    expect($payload['error'])->toBe('Failure')
        ->and($payload['meta']['status'])->toBe(SymfonyResponse::HTTP_INTERNAL_SERVER_ERROR);
});

it('throws when setting an invalid HTTP status code', function (): void {
    $request = Request::create('/test', 'GET');
    $builder = new ResponseBuilder($request, Carbon::now());

    expect(fn () => $builder->setStatus(99999))
        ->toThrow(UnexpectedValueException::class);
});

it('can handle collection and resource data and exposes resourceResponse', function (): void {
    $request = Request::create('/test', 'GET');
    $builder = new ResponseBuilder($request, Carbon::now());

    $items = collect([(object) ['id' => 1], (object) ['id' => 2]]);

    $builder->setData($items);

    $resource = $builder->getResourceResponse();

    expect($resource)->not->toBeNull()
        ->and($resource->resource)->toBeInstanceOf(Collection::class);

    $resourceData = new class($items) extends JsonResource {};

    $builder->setData($resourceData);

    expect($builder->getResourceResponse())->toBeInstanceOf(JsonResource::class);
});

it('serializes and unserializes response data', function (): void {
    config(['app.debug' => false]);

    $request = Request::create('/test', 'GET');
    $builder = new ResponseBuilder($request, Carbon::now());

    $builder->setData(['foo' => 'bar']);
    $builder->setStatus(SymfonyResponse::HTTP_CREATED);

    expect(fn () => $builder->serialize())
        ->not->toThrow(Throwable::class);
});
