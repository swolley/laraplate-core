<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use Approval\Models\Modification as ApprovalModification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Enums\CoreTables;
use Override;

/**
 * @property int|null $id
 * @property bool $active
 * @property int $approvers_required
 * @property int $disapprovers_required
 * @property string|null $modifiable_type
 * @property string|null $md5
 * @property int|null $modifier_id
 * @property string|null $modifier_type
 * @property bool $is_update
 * @property array<string, array{original: mixed, modified: mixed}>|null $modifications
 * @property-read array<string, mixed>|null $latest_automated_vote_meta
 * @mixin \Eloquent
 * @mixin IdeHelperModification
 */
final class Modification extends ApprovalModification
{
    use HasFactory;

    /**
     * @var string
     */
    #[Override]
    protected $table = CoreTables::Modifications->value;

    /**
     * @var array<int,string>
     *
     * @psalm-suppress NonInvariantPropertyType
     */
    #[Override]
    protected $hidden = [
        'modifiable_id',
        'modifiable_type',
        'modifier_id',
        'modifier_type',
        'md5',
        'active',
        'is_update',
        'approvers_required',
        'disapprovers_required',
    ];

    /**
     * Latest vote meta from approvals or disapprovals (e.g. AI moderation payload).
     *
     * @return array<string, mixed>|null
     */
    public function latestAutomatedVoteMeta(): ?array
    {
        /** @var Approval|null $approval */
        $approval = Approval::query()
            ->where('modification_id', $this->id)
            ->whereNotNull('meta')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['id', 'meta', 'created_at']);

        /** @var Disapproval|null $disapproval */
        $disapproval = Disapproval::query()
            ->where('modification_id', $this->id)
            ->whereNotNull('meta')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['id', 'meta', 'created_at']);

        if ($approval === null && $disapproval === null) {
            return null;
        }

        if ($approval === null) {
            /** @var array<string, mixed>|null */
            return $disapproval->meta;
        }

        if ($disapproval === null) {
            /** @var array<string, mixed>|null */
            return $approval->meta;
        }

        $latest = $approval->created_at >= $disapproval->created_at ? $approval : $disapproval;

        /** @var array<string, mixed>|null */
        return $latest->meta;
    }
}
