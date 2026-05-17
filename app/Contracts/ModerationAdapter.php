<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

use Modules\Core\Data\ModerationRequest;
use Modules\Core\Models\Modification;

interface ModerationAdapter
{
    public function supports(Modification $modification): bool;

    public function build(Modification $modification): ModerationRequest;
}
