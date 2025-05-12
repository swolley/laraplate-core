<?php

declare(strict_types=1);

namespace Modules\Core\Logging;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\PsrLogMessageProcessor;
use Override;

final readonly class GelfAdditionalInfoProcessor implements ProcessorInterface
{
    private PsrLogMessageProcessor $psrLogMessageProcessor;

    public function __construct(private ?string $channel = null)
    {
        $this->psrLogMessageProcessor = new PsrLogMessageProcessor(removeUsedContextFields: true);
    }

    #[Override]
    public function __invoke(LogRecord $record): LogRecord
    {
        $record = $this->psrLogMessageProcessor->__invoke($record);

        $extra = [
            'application_name' => config('app.name'),
            'channel' => $this->channel ?? config('logging.default'),
        ];

        if (! App::runningInConsole()) {
            $extra['user'] = Auth::user()?->username ?? 'anonymous';
            $extra['url'] = request()?->url();
        } else {
            $extra['user'] = 'console';
        }

        $record->extra = array_merge($record->extra, $extra);

        return $record;
    }
}
