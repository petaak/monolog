<?php declare(strict_types = 1);

namespace Contributte\Monolog\Holder;

use Monolog\Logger;
use Psr\Log\LoggerInterface;

abstract class LoggerHolder
{

	/** @var Logger */
	protected $instLogger;

	public function __construct(Logger $logger)
	{
		$this->instLogger = $logger;
	}

	/**
	 * @return static
	 */
	abstract public static function getInstance(): self;

	public function getLogger(): LoggerInterface
	{
		$backtrace = debug_backtrace();
		// Get class which called this or file if class does not exist
		$calledBy = $backtrace[1]['class'] ?? $backtrace[0]['file'];

		$logger = clone $this->instLogger;

		// Write in log which class used LoggerHolder
		$logger->pushProcessor(function (array $record) use ($calledBy): array {
			$record['extra']['calledBy'] = $calledBy;

			return $record;
		});

		return $logger;
	}

}
