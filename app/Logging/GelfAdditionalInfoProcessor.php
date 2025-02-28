<?php

declare(strict_types=1);

namespace Modules\Core\Logging;

use Monolog\LogRecord;
use Illuminate\Support\Facades\Auth;
use Monolog\Processor\ProcessorInterface;
use Monolog\Processor\PsrLogMessageProcessor;

class GelfAdditionalInfoProcessor implements ProcessorInterface
{
	private readonly PsrLogMessageProcessor $psrLogMessageProcessor;

	public function __construct(private readonly ?string $channel = null)
	{
		$this->psrLogMessageProcessor = new PsrLogMessageProcessor(removeUsedContextFields: true);
	}

	#[\Override]
 public function __invoke(LogRecord $record): LogRecord
	{
		$record = $this->psrLogMessageProcessor->__invoke($record);

		$extra = [
			'user' => Auth::user()->username ?? 'anonymous',
			'application_name' => config('app.name'),
			'channel' => $this->channel ?? config('logging.default'),
		];
		$record->extra = array_merge($record->extra, $extra);

		return $record;
	}
}
