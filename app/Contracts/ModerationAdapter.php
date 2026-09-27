<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Data\ModerationRequest;
use Modules\Core\Models\Modification;

interface ModerationAdapter
{
    /**
     * The model whose pending modifications this adapter can turn into a moderation request.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string;

    public function supports(Modification $modification): bool;

    public function build(Modification $modification): ModerationRequest;
}
