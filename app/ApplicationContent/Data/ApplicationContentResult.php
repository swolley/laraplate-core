<?php

declare(strict_types=1);

namespace Modules\Core\ApplicationContent\Data;

use InvalidArgumentException;

final readonly class ApplicationContentResult
{
    public string $source;

    /**
     * @param  list<ApplicationContentHit>  $hits
     */
    public function __construct(
        string $source,
        public array $hits,
        public string $strategy,
        public bool $truncated,
    ) {
        $this->source = ApplicationContentSourceDescriptor::normalizeSource($source);

        if (! array_is_list($this->hits)
            || preg_match('/^[a-z][a-z0-9_]{0,63}$/', $this->strategy) !== 1
            || count($this->hits) > 50) {
            throw new InvalidArgumentException('Application content result is invalid.');
        }

        foreach ($this->hits as $hit) {
            if (! $hit instanceof ApplicationContentHit
                || $hit->source !== $this->source
                || $hit->strategy !== $this->strategy) {
                throw new InvalidArgumentException('Application content result hit is invalid.');
            }
        }
    }
}
