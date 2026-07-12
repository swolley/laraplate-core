<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Graph\MaterializedGraphEdgeRepository;

uses(RefreshDatabase::class);

it('upserts materialized graph edges and reads non stale outgoing edges', function (): void {
    $repository = app(MaterializedGraphEdgeRepository::class);

    $repository->upsertMany([
        [
            'source_module' => 'cms',
            'source_entity' => 'contents',
            'source_key' => '10',
            'source_node_id' => 'cms:contents:10',
            'target_module' => 'cms',
            'target_entity' => 'tags',
            'target_key' => '3',
            'target_node_id' => 'cms:tags:3',
            'relation' => 'tags',
            'relation_path' => 'tags',
            'type' => 'tagged_as',
            'directed' => true,
            'metadata' => ['weight' => 1],
        ],
    ]);

    $edges = $repository->outgoingForSource('cms:contents:10');

    expect($edges)->toHaveCount(1);
    expect($edges[0]->source_node_id)->toBe('cms:contents:10');
    expect($edges[0]->target_node_id)->toBe('cms:tags:3');
    expect($edges[0]->metadata)->toBe(['weight' => 1]);

    $repository->upsertMany([
        [
            'source_module' => 'cms',
            'source_entity' => 'contents',
            'source_key' => '10',
            'source_node_id' => 'cms:contents:10',
            'target_module' => 'cms',
            'target_entity' => 'tags',
            'target_key' => '3',
            'target_node_id' => 'cms:tags:3',
            'relation' => 'tags',
            'relation_path' => 'tags',
            'type' => 'tagged_as',
            'directed' => true,
            'metadata' => ['weight' => 2],
        ],
    ]);

    expect($repository->outgoingForSource('cms:contents:10'))->toHaveCount(1);
    expect($repository->outgoingForSource('cms:contents:10')[0]->metadata)->toBe(['weight' => 2]);

    $repository->markStaleForSource('cms:contents:10');

    expect($repository->outgoingForSource('cms:contents:10'))->toHaveCount(0);
});
