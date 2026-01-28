<?php

declare(strict_types=1);

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Elastic\Elasticsearch\Response\Elasticsearch;
use GuzzleHttp\Psr7\Response;
use Modules\Core\Services\ElasticsearchService;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

uses(TestCase::class);

it('has proper class structure', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('getInstance'))->toBeTrue();
    expect($reflection->hasMethod('createIndex'))->toBeTrue();
    expect($reflection->hasMethod('search'))->toBeTrue();
});

it('has getInstance method as static', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);
    $method = $reflection->getMethod('getInstance');

    expect($method->isStatic())->toBeTrue();
    expect($method->isPublic())->toBeTrue();
});

it('has private constructor for singleton', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);
    $constructor = $reflection->getConstructor();

    expect($constructor->isPrivate())->toBeTrue();
});

it('has createIndex method with correct signature', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);
    $method = $reflection->getMethod('createIndex');

    expect($method->isPublic())->toBeTrue();
    expect($method->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
});

it('has search method with correct signature', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);
    $method = $reflection->getMethod('search');

    expect($method->isPublic())->toBeTrue();
    expect($method->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
});

it('has proper method visibility', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    $createIndexMethod = $reflection->getMethod('createIndex');
    $searchMethod = $reflection->getMethod('search');
    $getInstanceMethod = $reflection->getMethod('getInstance');

    expect($createIndexMethod->isPublic())->toBeTrue();
    expect($searchMethod->isPublic())->toBeTrue();
    expect($getInstanceMethod->isPublic())->toBeTrue();
});

it('has consistent method signatures', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    $createIndexMethod = $reflection->getMethod('createIndex');
    $searchMethod = $reflection->getMethod('search');

    expect($createIndexMethod->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
    expect($searchMethod->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
});

it('has proper class hierarchy', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->getName())->toBe(ElasticsearchService::class);
});

it('has required public methods', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    $publicMethods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    $methodNames = array_map(static fn ($method) => $method->getName(), $publicMethods);

    expect($methodNames)->toContain('createIndex');
    expect($methodNames)->toContain('search');
    expect($methodNames)->toContain('getInstance');
});

it('has proper class finality', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->isFinal())->toBeTrue();
});

it('has proper namespace', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->getName())->toBe('Modules\Core\Services\ElasticsearchService');
});

it('has proper method accessibility', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    $createIndexMethod = $reflection->getMethod('createIndex');
    $searchMethod = $reflection->getMethod('search');
    $getInstanceMethod = $reflection->getMethod('getInstance');

    expect($createIndexMethod->isPublic())->toBeTrue();
    expect($searchMethod->isPublic())->toBeTrue();
    expect($getInstanceMethod->isPublic())->toBeTrue();
});

it('has proper class structure for elasticsearch', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('createIndex'))->toBeTrue();
    expect($reflection->hasMethod('search'))->toBeTrue();
});

it('has proper method parameter types', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    $createIndexMethod = $reflection->getMethod('createIndex');
    $searchMethod = $reflection->getMethod('search');

    expect($createIndexMethod->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
    expect($searchMethod->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
});

it('has proper class structure for singleton pattern', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('getInstance'))->toBeTrue();

    $constructor = $reflection->getConstructor();
    expect($constructor->isPrivate())->toBeTrue();
});

it('has proper method return types', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    $createIndexMethod = $reflection->getMethod('createIndex');
    $searchMethod = $reflection->getMethod('search');

    expect($createIndexMethod->getReturnType())->not->toBeNull();
    expect($searchMethod->getReturnType())->not->toBeNull();
});

it('has proper class structure for service pattern', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('createIndex'))->toBeTrue();
    expect($reflection->hasMethod('search'))->toBeTrue();
});

it('has proper method signatures for elasticsearch', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    $createIndexMethod = $reflection->getMethod('createIndex');
    $searchMethod = $reflection->getMethod('search');

    expect($createIndexMethod->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
    expect($searchMethod->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
});

it('has proper class structure for API integration', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('createIndex'))->toBeTrue();
    expect($reflection->hasMethod('search'))->toBeTrue();
});

it('has proper method structure for elasticsearch operations', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    $createIndexMethod = $reflection->getMethod('createIndex');
    $searchMethod = $reflection->getMethod('search');

    expect($createIndexMethod->isPublic())->toBeTrue();
    expect($searchMethod->isPublic())->toBeTrue();
});

it('has proper class structure for singleton service', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->isFinal())->toBeTrue();
    expect($reflection->hasMethod('getInstance'))->toBeTrue();

    $constructor = $reflection->getConstructor();
    expect($constructor->isPrivate())->toBeTrue();
});

it('has proper method structure for API calls', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    $createIndexMethod = $reflection->getMethod('createIndex');
    $searchMethod = $reflection->getMethod('search');

    expect($createIndexMethod->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
    expect($searchMethod->getNumberOfParameters())->toBeGreaterThanOrEqual(1);
});

it('has all required CRUD methods', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->hasMethod('createIndex'))->toBeTrue();
    expect($reflection->hasMethod('deleteIndex'))->toBeTrue();
    expect($reflection->hasMethod('getDocument'))->toBeTrue();
    expect($reflection->hasMethod('deleteDocument'))->toBeTrue();
});

it('has search and utility methods', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->hasMethod('search'))->toBeTrue();
});

it('has bulk operation methods', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->hasMethod('bulkIndex'))->toBeTrue();
});

it('has all required CRUD methods', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->hasMethod('createIndex'))->toBeTrue();
    expect($reflection->hasMethod('deleteIndex'))->toBeTrue();
    expect($reflection->hasMethod('getDocument'))->toBeTrue();
    expect($reflection->hasMethod('deleteDocument'))->toBeTrue();
});

it('has bulk operation methods', function (): void {
    $reflection = new ReflectionClass(ElasticsearchService::class);

    expect($reflection->hasMethod('bulkIndex'))->toBeTrue();
});

beforeEach(function (): void {
    resetElasticsearchSingleton();
});

it('creates index when missing', function (): void {
    $mock_client = makeClientWithResponses([
        new Response(404, [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME]),
        new Response(200, [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME], json_encode(['acknowledged' => true])),
    ]);

    setElasticsearchInstance($mock_client);
    $service = ElasticsearchService::getInstance();

    expect($service->createIndex('my-index'))->toBeTrue();
});

it('updates mappings and settings when index exists', function (): void {
    $mock_client = makeClientWithResponses([
        new Response(200, [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME]),
        new Response(200, [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME], json_encode(['acknowledged' => true])),
        new Response(200, [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME], json_encode(['acknowledged' => true])),
    ]);

    setElasticsearchInstance($mock_client);
    $service = ElasticsearchService::getInstance();

    expect($service->createIndex(
        'my-index',
        ['number_of_shards' => 1],
        ['properties' => ['foo' => ['type' => 'keyword']]],
    ))->toBeTrue();
});

it('deletes index gracefully if missing', function (): void {
    $mock_client = makeClientWithResponses([
        new Response(404, [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME]),
    ]);

    setElasticsearchInstance($mock_client);
    $service = ElasticsearchService::getInstance();

    expect($service->deleteIndex('missing-index'))->toBeTrue();
});

it('bulk indexes documents and counts successes/failures', function (): void {
    $bulk_response = [
        'items' => [
            ['index' => ['status' => 201]],
            ['index' => ['status' => 500, 'error' => 'boom']],
        ],
    ];

    $mock_client = makeClientWithResponses([
        new Response(200, [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME], json_encode($bulk_response)),
    ]);

    setElasticsearchInstance($mock_client);
    $service = ElasticsearchService::getInstance();

    $result = $service->bulkIndex('idx', [
        1 => ['foo' => 'bar'],
        2 => ['foo' => 'baz'],
    ]);

    expect($result)->toMatchArray([
        'indexed' => 1,
        'failed' => 1,
        'errors' => ['boom'],
    ]);
});

it('searches and returns array payload', function (): void {
    $body = ['hits' => ['hits' => [['foo' => 'bar']]]];
    $mock_client = makeClientWithResponses([
        new Response(200, [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME], json_encode($body)),
    ]);

    setElasticsearchInstance($mock_client);
    $service = ElasticsearchService::getInstance();

    expect($service->search('idx', ['match_all' => (object) []]))->toBe($body);
});

it('returns null when document is not found', function (): void {
    $mock_client = makeClientWithResponses([
        new Response(404, [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME]),
    ]);

    setElasticsearchInstance($mock_client);
    $service = ElasticsearchService::getInstance();

    expect($service->getDocument('idx', 'missing'))->toBeNull();
});

it('deletes document when present and returns true', function (): void {
    $mock_client = makeClientWithResponses([
        new Response(200, [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME]),
        new Response(200, [Elasticsearch::HEADER_CHECK => Elasticsearch::PRODUCT_NAME], json_encode(['deleted' => true])),
    ]);

    setElasticsearchInstance($mock_client);
    $service = ElasticsearchService::getInstance();

    expect($service->deleteDocument('idx', '1'))->toBeTrue();
});

function makeClientWithResponses(array $responses): Client
{
    /**
     * Simple queue-based PSR-18 client that returns canned responses.
     */
    $http_client = new class implements ClientInterface
    {
        /**
         * @var array<int,ResponseInterface>
         */
        public array $responses;

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            if ($this->responses === []) {
                throw new RuntimeException('No more queued responses for Elasticsearch mock client.');
            }

            return array_shift($this->responses);
        }
    };

    $http_client->responses = $responses;

    return ClientBuilder::create()
        ->setHttpClient($http_client)
        ->build();
}

function setElasticsearchInstance(Client $client): ElasticsearchService
{
    $reflection = new ReflectionClass(ElasticsearchService::class);
    $instance = $reflection->newInstanceWithoutConstructor();

    $client_property = $reflection->getProperty('client');
    $client_property->setAccessible(true);
    $client_property->setValue($instance, $client);

    $instance_property = $reflection->getProperty('instance');
    $instance_property->setAccessible(true);
    $instance_property->setValue(null, $instance);

    return $instance;
}

function resetElasticsearchSingleton(): void
{
    $reflection = new ReflectionClass(ElasticsearchService::class);
    $instance_property = $reflection->getProperty('instance');
    $instance_property->setAccessible(true);
    $instance_property->setValue(null, null);
}
