<?php declare(strict_types = 1);

namespace Contributte\Monolog\Holder;

use Contributte\Monolog\Exception\Logic\InvalidStateException;
use Monolog\Logger;
use Nette\DI\Container;

class LazyLoggerHolder extends LoggerHolder
{

	/** @var static|null */
	private static $instSelf;

	/** @var string|null */
	private static $loggerServiceName;

	/** @var Container|null */
	private static $container;

	public static function getInstance(): LoggerHolder
	{
		if (static::$instSelf === null) {
			if (static::$loggerServiceName === null || static::$container === null) {
				throw new InvalidStateException(sprintf('Call %s::setLogger to use %s::getInstance', static::class, static::class));
			}

			/** @var Logger $logger */
			$logger = static::$container->getService(static::$loggerServiceName);
			static::$instSelf = new static($logger);
		}

		return static::$instSelf;
	}

	public static function setLogger(string $loggerServiceName, Container $container): void
	{
		static::$loggerServiceName = $loggerServiceName;
		static::$container = $container;
	}

}
