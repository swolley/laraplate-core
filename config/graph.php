<?php

declare(strict_types=1);

return [
    'max_depth' => 3,
    'default_limit' => 100,
    'max_limit' => 200,
    'default_relation_limit' => 25,
    'max_relation_limit' => 100,
    'default_node_detail' => 'summary',
    'assistant_safe_fields' => [
        'default' => ['title', 'name', 'label', 'slug', 'status', 'type', 'code'],
        'core.users' => ['name'],
    ],
];
