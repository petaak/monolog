<?php declare(strict_types = 1);

namespace Contributte\Monolog\Holder;

use Contributte\Monolog\Exception\Logic\InvalidStateException;
use Monolog\Logger;

class SimpleLoggerHolder extends LoggerHolder
{

	/** @var Logger|null */
	private static $logger;

	/** @var static|null */
	private static $instSelf;

	public static function getInstance(): LoggerHolder
	{
		if (static::$logger === null) {
			throw new InvalidStateException(sprintf('Call %s::setLogger to use %s::getInstance', static::class, static::class));
		}

		if (static::$instSelf === null) {
			static::$instSelf = new static(static::$logger);
		}

		return static::$instSelf;
	}

	public static function setLogger(Logger $logger): void
	{
		static::$logger = $logger;
	}

}
