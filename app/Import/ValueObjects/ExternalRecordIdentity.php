<?php

declare(strict_types=1);

namespace Modules\Core\Import\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final readonly class ExternalRecordIdentity
{
    public function __construct(
        public string $sourceKey,
        public ?string $externalId,
        public ?string $fingerprint = null,
        public ?CarbonImmutable $sourceUpdatedAt = null,
    ) {
        if (mb_trim($this->sourceKey) === '') {
            throw new InvalidArgumentException('External record source key cannot be empty.');
        }

        if ($this->externalId !== null && mb_trim($this->externalId) === '') {
            throw new InvalidArgumentException('External record id cannot be empty when provided.');
        }

        if ($this->fingerprint !== null && preg_match('/\A[a-f0-9]{64}\z/', $this->fingerprint) !== 1) {
            throw new InvalidArgumentException('External record fingerprint must be a lowercase SHA-256 hash.');
        }
    }
}
