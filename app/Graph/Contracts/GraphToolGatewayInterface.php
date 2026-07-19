<?php

declare(strict_types=1);

namespace Modules\Core\Graph\Contracts;

use Modules\Core\Graph\Data\GraphExpandToolInput;
use Modules\Core\Graph\Data\GraphSearchToolInput;
use Modules\Core\Graph\Data\GraphStatsToolInput;

interface GraphToolGatewayInterface
{
    /**
     * @return array<string, mixed>
     */
    public function search(GraphSearchToolInput $input): array;

    /**
     * @return array<string, mixed>
     */
    public function expand(GraphExpandToolInput $input): array;

    /**
     * @return array<string, mixed>
     */
    public function stats(GraphStatsToolInput $input): array;
}
