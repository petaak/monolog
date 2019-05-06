<?php declare(strict_types = 1);

namespace Contributte\Monolog\DI;

use Contributte\Monolog\Exception\Logic\InvalidArgumentException;
use Contributte\Monolog\Exception\Logic\InvalidStateException;
use Contributte\Monolog\Manager\LazyLoggerManager;
use Contributte\Monolog\Manager\LoggerManager;
use Contributte\Monolog\Manager\SimpleLoggerManager;
use Contributte\Monolog\Tracy\LazyTracyLogger;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\Container;
use Nette\DI\Statement;
use Nette\PhpGenerator\ClassType;
use Nette\Utils\Strings;
use Tracy\Bridges\Psr\PsrToTracyLoggerAdapter;
use Tracy\Bridges\Psr\TracyToPsrLoggerAdapter;
use Tracy\Debugger;

class MonologExtension extends CompilerExtension
{

	/** @var mixed[] */
	private $defaults = [
		'channel' => [],
		'hook' => [
			'fromTracy' => true, // log through Tracy
			'toTracy' => true, // log through Monolog
			'lazyTracy' => true,
		],
		'holder' => [
			'enabled' => false,
			'lazy' => true,
		],
		'manager' => [
			'enabled' => false,
			'lazy' => true,
		],
	];

	/** @var mixed [] */
	private $channelDefaults = [
		'handlers' => [],
		'processors' => [],
	];

	public function loadConfiguration(): void
	{
		$config = $this->validateConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		if (!isset($config['channel']['default'])) {
			throw new InvalidStateException(sprintf('%s.channel.default is required.', $this->name));
		}

		if ($config['manager']['enabled']) {
			$manager = $builder->addDefinition($this->prefix('manager'))
				->setType(LoggerManager::class);

			if ($config['manager']['lazy']) {
				$manager->setFactory(LazyLoggerManager::class, ['prefix' => $this->prefix('logger')]);
			} else {
				$manager->setFactory(SimpleLoggerManager::class);
			}
		}

		$tracyHandler = null;

		if (class_exists(Debugger::class) && $config['hook']['toTracy'] && $builder->hasDefinition('tracy.logger')) {
			$tracyAdapter = new Statement(TracyToPsrLoggerAdapter::class);
			$tracyAdapter->arguments = ['@tracy.logger'];

			$tracyHandler = new Statement(PsrHandler::class);
			$tracyHandler->arguments = [$tracyAdapter];
		}

		foreach ($config['channel'] as $name => $channel) {
			$channel = $this->validateConfig($this->channelDefaults, $channel, $this->prefix('channel.' . $name));

			if (!is_string($name)) {
				throw new InvalidArgumentException(sprintf('%s.channel.%s name must be a string', $this->name, (string) $name));
			}

			if ($tracyHandler !== null) {
				$channel['handlers']['tracy'] = $tracyHandler;
			}

			if (!isset($channel['handlers']) || $channel['handlers'] === []) {
				throw new InvalidStateException(sprintf('%s.channel.%s.handlers must contain at least one handler', $this->name, $name));
			}

			// Register handlers same way as services (setup, arguments, type etc.)
			foreach ($channel['handlers'] as $handlerKey => $handlerValue) {
				// Don't register handler as service, it's already registered service
				if (is_string($handlerValue) && Strings::startsWith($handlerValue, '@')) {
					continue;
				}

				$handlerName = $this->prefix('logger.' . $name . '.handler.' . $handlerKey);
				$handler = $builder->addDefinition($handlerName)
					->setAutowired(false);

				Compiler::loadDefinition($handler, $handlerValue);
				$channel['handlers'][$handlerKey] = '@' . $handlerName;
			}

			// Register processors same way as services (setup, arguments, type etc.)
			if (isset($channel['processors'])) {
				foreach ($channel['processors'] as $processorKey => $processorValue) {
					// Don't register processor as service, it's already registered service
					if (is_string($processorValue) && Strings::startsWith($processorValue, '@')) {
						continue;
					}

					$processorName = $this->prefix('logger.' . $name . '.processor.' . $processorKey);
					$processor = $builder->addDefinition($processorName)
						->setAutowired(false);

					Compiler::loadDefinition($processor, $processorValue);
					$channel['processors'][$processorKey] = '@' . $processorName;
				}
			}

			$logger = $builder->addDefinition($this->prefix('logger.' . $name))
				->setType(Logger::class)
				->setArguments([
					$name,
					$channel['handlers'],
					$channel['processors'] ?? [],
				]);

			if ($config['manager']['enabled'] === true && $config['manager']['lazy'] !== true) {
				$manager->addSetup('add', [$logger]);
			}

			// Only default logger is autowired
			if ($name !== 'default') {
				$logger->setAutowired(false);
			}
		}

		if (class_exists(Debugger::class) && $config['hook']['fromTracy'] && $builder->hasDefinition('tracy.logger')) {
			$builder->getDefinition('tracy.logger')
				->setAutowired(false);

			$builder->addDefinition($this->prefix('psrToTracyAdapter'))
				->setFactory(PsrToTracyLoggerAdapter::class)
				->setAutowired(false);

			if ($config['hook']['lazyTracy']) {
				$builder->addDefinition($this->prefix('psrToTracyLazyAdapter'))
					->setFactory(LazyTracyLogger::class, [$this->prefix('psrToTracyAdapter')])
					->setAutowired(false);
			}
		}
	}

	public function afterCompile(ClassType $class): void
	{
		$builder = $this->getContainerBuilder();
		$config = $this->validateConfig($this->defaults);
		$initialize = $class->getMethod('initialize');

		if (class_exists(Debugger::class) && $config['hook']['fromTracy'] && $builder->hasDefinition('tracy.logger')) {
			$tracyLogger = $config['hook']['lazyTracy']
				? $this->prefix('@psrToTracyLazyAdapter')
				: $this->prefix('@psrToTracyAdapter');

			$initialize->addBody($builder->formatPhp('Tracy\Debugger::setLogger(?);', [$tracyLogger]));
		}

		if ($config['holder']['enabled']) {
			if ($config['holder']['lazy']) {
				$initialize->addBody('Contributte\Monolog\Holder\LazyLoggerHolder::setLogger($this->getByType(?));', [$this->prefix('logger.default'), Container::class]);
			} else {
				$initialize->addBody('Contributte\Monolog\Holder\SimpleLoggerHolder::setLogger($this->getByType(?));', [Logger::class]);
			}
		}
	}

}
