<?php

declare(strict_types=1);

namespace Modules\Core\Import\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Modules\Core\Import\Enums\ExternalRecordState;
use Modules\Core\Import\ValueObjects\ExternalRecordIdentity;
use Modules\Core\Models\RecordOrigin;

final class RecordOriginRegistry
{
    public function inspect(Model $referable, ExternalRecordIdentity $identity): ExternalRecordState
    {
        $origin = $this->identityQuery($referable, $identity)->first(['fingerprint']);

        if ($origin === null) {
            return ExternalRecordState::Missing;
        }

        if ($identity->fingerprint === null) {
            return ExternalRecordState::Unchanged;
        }

        $stored_fingerprint = is_string($origin->fingerprint) ? $origin->fingerprint : '';

        return hash_equals($stored_fingerprint, $identity->fingerprint)
            ? ExternalRecordState::Unchanged
            : ExternalRecordState::Changed;
    }

    public function referableId(Model $referable, string $source_key, string $external_id): ?int
    {
        $id = $this->connectionFor($referable)
            ->table((new RecordOrigin)->getTable())
            ->where('referable_type', $referable->getMorphClass())
            ->where('source_key', $source_key)
            ->where('external_id', $external_id)
            ->value('referable_id');

        return $id === null ? null : (int) $id;
    }

    public function register(
        Model $referable,
        ExternalRecordIdentity $identity,
        ?string $source_label = null,
        ?string $url = null,
    ): void {
        $now = now();
        $identity_values = [
            'referable_type' => $referable->getMorphClass(),
            'source_key' => $identity->sourceKey,
            'external_id' => $identity->externalId,
        ];

        if ($identity->externalId === null) {
            $identity_values['referable_id'] = $referable->getKey();
        }

        $values = [
            'referable_id' => $referable->getKey(),
            'source_label' => $source_label,
            'url' => $url,
            'updated_at' => $now,
        ];

        if ($identity->fingerprint !== null) {
            $values['fingerprint'] = $identity->fingerprint;
        }

        if ($identity->sourceUpdatedAt !== null) {
            $values['source_updated_at'] = $identity->sourceUpdatedAt;
        }

        $this->connectionFor($referable)
            ->table((new RecordOrigin)->getTable())
            ->updateOrInsert(
                $identity_values,
                static fn (bool $exists): array => $exists
                    ? $values
                    : [...$values, 'created_at' => $now],
            );
    }

    private function identityQuery(Model $referable, ExternalRecordIdentity $identity): Builder
    {
        return $this->connectionFor($referable)
            ->table((new RecordOrigin)->getTable())
            ->where('referable_type', $referable->getMorphClass())
            ->where('source_key', $identity->sourceKey)
            ->when(
                $identity->externalId !== null,
                static fn (Builder $query): Builder => $query->where('external_id', $identity->externalId),
                static fn (Builder $query): Builder => $query
                    ->whereNull('external_id')
                    ->where('referable_id', $referable->getKey()),
            );
    }

    private function connectionFor(Model $referable): ConnectionInterface
    {
        return $referable->getConnection();
    }
}
