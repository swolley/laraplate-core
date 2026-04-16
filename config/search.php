<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Engine predefinito
    |--------------------------------------------------------------------------
    |
    | Definisce l'engine di ricerca predefinito da utilizzare quando non ne viene
    | specificato uno a livello di modello. Deve essere uno degli engine configurati.
    |
    */
    'default' => env('SEARCH_ENGINE', 'elasticsearch'),

    /*
    |--------------------------------------------------------------------------
    | Feature flags
    |--------------------------------------------------------------------------
    */
    'features' => [
        'reranker' => env('SEARCH_RERANKER_ENABLED', true),
        'ensemble' => env('SEARCH_ENSEMBLE_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reranker configuration
    |--------------------------------------------------------------------------
    */
    'reranker' => [
        'top_k' => (int) env('SEARCH_RERANKER_TOP_K', 30),
        'weight' => (float) env('SEARCH_RERANKER_WEIGHT', 0.5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ensemble search defaults
    |--------------------------------------------------------------------------
    */
    'ensemble' => [
        'keyword_weight' => 0.35,
        'vector_weight' => 0.35,
        'hybrid_weight' => 0.30,
        'agreement_boost' => 0.15,
        'rrf_k' => 60,
        'rrf_weight' => 0.25,
    ],

    /*
    |--------------------------------------------------------------------------
    | Vector Search
    |--------------------------------------------------------------------------
    |
    | Configurazioni per la ricerca vettoriale (embedding).
    |
    */
    'vector_search' => [
        'enabled' => env('VECTOR_SEARCH_ENABLED', true),
        'dimension' => env('VECTOR_DIMENSION', 768), // Default per OpenAI text-embedding-3-small
        'similarity' => env('VECTOR_SIMILARITY', 'cosine'), // cosine, dot_product, euclidean
    ],

    /*
    |--------------------------------------------------------------------------
    | Engines configurati
    |--------------------------------------------------------------------------
    |
    | Configurazioni per i vari engine di ricerca supportati.
    |
    */
    'engines' => [
        'elasticsearch' => [
            'class' => Modules\Core\Search\Engines\ElasticsearchEngine::class,
            'index_prefix' => env('ELASTIC_INDEX_PREFIX', ''),
        ],
        'typesense' => [
            'class' => Modules\Core\Search\Engines\TypesenseEngine::class,
            'index_prefix' => env('TYPESENSE_INDEX_PREFIX', ''),
            'api_key' => env('TYPESENSE_API_KEY'),
            'hosts' => explode(',', env('TYPESENSE_HOSTS', 'http://localhost:8108')),
        ],
    ],
];
