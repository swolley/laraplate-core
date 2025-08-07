<?php

declare(strict_types=1);

namespace Modules\Core\Search\Ai;

final class SentenceTransformersConfig
{
    public int $timeout = 10;

    public bool $truncate = true;

    public bool $normalizeEmbeddings = true;

    public function __construct(private ?string $apiKey = null, private string $url = 'http://localhost:8000') {}
}
