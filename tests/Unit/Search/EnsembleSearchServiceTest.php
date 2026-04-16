<?php

declare(strict_types=1);

use Modules\Core\Search\Contracts\IReranker;
use Modules\Core\Search\Services\EnsembleSearchService;
use Modules\Core\Search\Services\HeuristicReranker;

beforeEach(function (): void {
    $this->reranker = new HeuristicReranker;
    $this->service = new EnsembleSearchService($this->reranker);
});

it('accepts IReranker in constructor', function (): void {
    $mock_reranker = Mockery::mock(IReranker::class);
    $service = new EnsembleSearchService($mock_reranker);
    expect($service)->toBeInstanceOf(EnsembleSearchService::class);
});

it('returns empty results when no strategies execute', function (): void {
    $plan = [
        'retrieval' => ['use_fulltext' => false, 'use_vector' => false, 'size' => 10],
        'ensemble' => [],
        'ranking' => ['use_reranker' => false],
    ];

    $result = $this->service->search(
        ['keywords' => [], 'date_range' => null, 'query' => ['expanded' => '']],
        null,
        'test',
        $plan,
        'test_index',
    );

    expect($result['results'])->toBe([]);
    expect($result['meta']['strategies_executed'])->toBe(0);
});
