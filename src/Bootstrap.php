<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests;

use Bitrix\Main\Application;
use Composer\Autoload\ClassLoader;
use Kosmosafive\Bitrix\Tests\Diag\ExceptionHandlerLog;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;
use Symfony\Component\Dotenv\Exception\PathException;

class Bootstrap
{
    public function __construct(
        protected readonly ClassLoader $classLoader,
        protected readonly string $documentRoot,
        protected readonly string $configPath,
        protected readonly string $dataDir,
        protected readonly array $autoload = []
    ) {
    }

    protected function setUp(): void
    {
        $this->loadEnv();

        if (isset($_ENV['SYMFONY_DOTENV_VARS'])) {
            foreach (explode(',', (string) $_ENV['SYMFONY_DOTENV_VARS']) as $var) {
                if (isset($_ENV[$var]) && !defined($var)) {
                    define($var, $_ENV[$var]);
                }
            }
        }

        if (!defined('DATA_DIR')) {
            define('DATA_DIR', $this->dataDir);
        }

        $_SERVER['DOCUMENT_ROOT'] = $this->documentRoot;
    }

    public function initialize(): void
    {
        $this->setUp();

        require $this->documentRoot
            . DIRECTORY_SEPARATOR . 'bitrix'
            . DIRECTORY_SEPARATOR . 'modules'
            . DIRECTORY_SEPARATOR . 'main'
            . DIRECTORY_SEPARATOR . 'cli'
            . DIRECTORY_SEPARATOR . 'bootstrap.php';

        $this->setExceptionHandler();
        $this->addAutoLoadClasses();
        $this->loadExpectations();
    }

    protected function loadEnv(): void
    {
        $helper = new Console\Helper();
        $symfonyStyle = $helper->getStyler();

        $dotenv = new Dotenv();
        try {
            $dotenv->loadEnv($this->configPath);
        } catch (FormatException $exception) {
            $symfonyStyle->error('The configuration file is in an invalid format.');
            throw $exception;
        } catch (PathException $exception) {
            $symfonyStyle->error('Configuration file ' . $this->configPath . ' is missing');
            throw $exception;
        }
    }

    protected function setExceptionHandler(): void
    {
        $exceptionHandlerLog = new ExceptionHandlerLog();
        $exceptionHandlerLog->initialize(['level' => $GLOBALS['LOG_LEVEL']]);

        $bitrixExceptionHandler = Application::getInstance()->getExceptionHandler();
        $bitrixExceptionHandler->setHandlerLog($exceptionHandlerLog);
        $bitrixExceptionHandler->setHandledErrorsTypes(
            E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR
        );
        $bitrixExceptionHandler->setExceptionErrorsTypes(
            E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR
        );
    }

    protected function addAutoLoadClasses(): void
    {
        foreach ($this->autoload as [$prefix, $paths]) {
            $this->classLoader->setPsr4($prefix, $paths);
        }
    }

    protected function loadExpectations(): void
    {
        include __DIR__ . DIRECTORY_SEPARATOR . 'Pest' . DIRECTORY_SEPARATOR . 'Expectations.php';
    }
}
