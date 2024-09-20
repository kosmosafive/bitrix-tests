<?php

declare(strict_types=1);

namespace Kosmos\BitrixTests;

use Bitrix\Main\IO;
use Bitrix\Main\Application;
use Kosmos\BitrixTests\Diag\ExceptionHandlerLog;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Dotenv\Exception\FormatException;
use Symfony\Component\Dotenv\Exception\PathException;
use Composer\Autoload\ClassLoader;

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

    public function initialize(): void
    {
        $this->setUp();

        require(
            $this->documentRoot
            .DIRECTORY_SEPARATOR.'bitrix'
            .DIRECTORY_SEPARATOR.'modules'
            .DIRECTORY_SEPARATOR.'main'
            .DIRECTORY_SEPARATOR.'cli'
            .DIRECTORY_SEPARATOR.'bootstrap.php'
        );

        $this->setExceptionHandler();
        $this->addAutoLoadClasses();
        $this->loadExpectations();
    }

    protected function setUp(): void
    {
        $this->loadEnv();

        if (isset($_ENV['SYMFONY_DOTENV_VARS'])) {
            foreach (explode(',', $_ENV['SYMFONY_DOTENV_VARS']) as $var) {
                if (isset($_ENV[$var]) && !defined($var)) {
                    define($var, $_ENV[$var]);
                }
            }
        }

        if(!defined('DATA_DIR')) {
            define('DATA_DIR', $this->dataDir);
        }

        $_SERVER['DOCUMENT_ROOT'] = $this->documentRoot;
    }

    protected function loadEnv(): void
    {
        $console = new Console\Helper();
        $styler = $console->getStyler();

        $dotenv = new Dotenv();
        try {
            $dotenv->loadEnv($this->configPath);
        } catch (FormatException $exception) {
            $styler->error('The configuration file is in an invalid format.');
            throw $exception;
        } catch (PathException $exception) {
            $styler->error('Configuration file '.$this->configPath.' is missing');
            throw $exception;
        }
    }

    protected function setExceptionHandler(): void
    {
        $handler = new ExceptionHandlerLog();
        $handler->initialize(['level' => $GLOBALS['LOG_LEVEL']]);

        $bitrixExceptionHandler = Application::getInstance()->getExceptionHandler();
        $bitrixExceptionHandler->setHandlerLog($handler);
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
        include __DIR__ . DIRECTORY_SEPARATOR. 'Pest'.DIRECTORY_SEPARATOR.'Expectations.php';
    }
}
