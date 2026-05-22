<?php

declare(strict_types=1);

namespace Modules\Core\Tests\Fixtures;

use Modules\Core\Logging\GelfAdditionalInfoProcessor;
use Monolog\LogRecord;

final class GelfProcessorInvoker
{
    public static function invoke(GelfAdditionalInfoProcessor $processor, LogRecord $record): LogRecord
    {
        return $processor($record);
    }
}
