<?php declare(strict_types = 1);

namespace Contributte\Monolog\Manager;

use Psr\Log\LoggerInterface;

interface LoggerManager
{

	public function has(string $name): bool;

	public function get(string $name): LoggerInterface;

}
