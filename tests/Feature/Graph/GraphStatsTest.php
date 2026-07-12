<?php

declare(strict_types=1);

use Modules\Core\Graph\GraphStatsCalculator;

it('calculates totals and distributions from expanded graph data', function (): void {
    $graph = [
        'center' => 'cms:contents:10',
        'nodes' => [
            ['id' => 'cms:contents:10', 'module' => 'cms', 'entity' => 'contents'],
            ['id' => 'cms:tags:2', 'module' => 'cms', 'entity' => 'tags'],
            ['id' => 'core:users:1', 'module' => 'core', 'entity' => 'users'],
        ],
        'edges' => [
            ['id' => 'edge:one', 'source' => 'cms:contents:10', 'target' => 'cms:tags:2', 'relation' => 'tags', 'type' => 'tagged_as'],
            ['id' => 'edge:two', 'source' => 'cms:contents:10', 'target' => 'core:users:1', 'relation' => 'author', 'type' => null],
            ['id' => 'edge:three', 'source' => 'core:users:1', 'target' => 'cms:contents:10', 'relation' => 'contents', 'type' => 'authored'],
        ],
    ];

    $stats = app(GraphStatsCalculator::class)->fromGraph($graph)->toArray();

    expect($stats)->toBe([
        'totalNodes' => 3,
        'totalEdges' => 3,
        'nodesByModule' => [
            'cms' => 2,
            'core' => 1,
        ],
        'nodesByEntity' => [
            'cms:contents' => 1,
            'cms:tags' => 1,
            'core:users' => 1,
        ],
        'edgesByRelation' => [
            'author' => 1,
            'contents' => 1,
            'tags' => 1,
        ],
        'edgesByType' => [
            'authored' => 1,
            'tagged_as' => 1,
        ],
    ]);
});
