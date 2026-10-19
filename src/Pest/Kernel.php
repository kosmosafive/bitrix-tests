<?php

declare(strict_types=1);

namespace Kosmosafive\Bitrix\Tests\Pest;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Bitrix\Main\Engine\AutoWire\Binder;
use Bitrix\Main\Engine\AutoWire\Parameter;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\ControllerBuilder;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Engine\JsonPayload;
use Bitrix\Main\Engine\Response\AjaxJson;
use Bitrix\Main\ErrorCollection;
use Bitrix\Main\HttpApplication;
use Bitrix\Main\HttpResponse;
use Bitrix\Main\Loader;
use Bitrix\Main\UI\PageNavigation;
use RuntimeException;

/**
 * In-process аналог routing_index.php.
 * HttpApplication::runController() вызывает Application::end() и exit(), поэтому
 * действие запускается через Controller::run(), а ответ читается из объекта.
 */
final class Kernel
{
    private static bool $autoWireRegistered = false;

    /**
     * @param array<string, scalar|null> $query
     * @param array<string, mixed> $body
     */
    public static function request(
        string $method,
        string $uri,
        array $query = [],
        array $body = [],
        bool $withCsrf = true,
    ): JsonResponse {
        $method = strtoupper($method);
        $parts = parse_url($uri);
        if (!is_array($parts) || !isset($parts['path']) || !str_starts_with($parts['path'], '/')) {
            throw new RuntimeException('API uri must be an absolute path: ' . $uri);
        }

        $uriQuery = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $uriQuery);
        }

        $get = array_merge($uriQuery, $query);
        if ($withCsrf && !array_key_exists('sessid', $get) && !array_key_exists('sessid', $body)) {
            $get['sessid'] = bitrix_sessid();
        }

        $post = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) ? $body : [];
        $server = self::server($method, $parts['path'], $get);

        $previousGet = $_GET;
        $previousPost = $_POST;
        $previousRequest = $_REQUEST;
        $previousServer = $_SERVER;

        try {
            $application = self::application();
            self::applySuperglobals($get, $post, $server);
            $application->initializeContext(self::contextParameters());

            $route = $application->getRouter()->match(Context::getCurrent()->getRequest());
            if ($route === null) {
                throw new RuntimeException('No route matched ' . $method . ' ' . $parts['path']);
            }

            $parameters = $route->getParametersValues()->getValues();
            if ($parameters !== []) {
                $get = array_merge($get, $parameters);
                self::applySuperglobals($get, $post, $server);
                $application->initializeContext(self::contextParameters());
            }

            $application->setCurrentRoute($route);

            $controller = $route->getController();
            if (!is_array($controller) || !isset($controller[0], $controller[1])) {
                throw new RuntimeException('Only [controller, action] routes can be dispatched in-process.');
            }

            [$controllerClass, $actionName] = $controller;
            if (!is_string($controllerClass) || !is_string($actionName)) {
                throw new RuntimeException('Route controller must be a class and action name.');
            }

            Loader::requireClass($controllerClass);
            if (str_ends_with($actionName, 'Action')) {
                $actionName = substr($actionName, 0, -6);
            }

            self::registerAutoWirings();

            $engine = ControllerBuilder::build($controllerClass, [
                'scope' => Controller::SCOPE_AJAX,
                'currentUser' => CurrentUser::get(),
            ]);

            $request = Context::getCurrent()->getRequest();
            $result = $engine->run($actionName, [
                $request->getPostList(),
                $request->getQueryList(),
            ]);

            return self::response($result, $engine);
        } finally {
            $_GET = $previousGet;
            $_POST = $previousPost;
            $_REQUEST = $previousRequest;
            $_SERVER = $previousServer;
        }
    }

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @param array<string, string> $server
     */
    private static function applySuperglobals(array $get, array $post, array $server): void
    {
        $_GET = $get;
        $_POST = $post;
        $_REQUEST = array_merge($get, $post);
        $_SERVER = array_merge($_SERVER, $server);
    }

    /**
     * @return array{get: array, post: array, files: array, cookie: array, server: array, env: array}
     */
    private static function contextParameters(): array
    {
        return [
            'get' => $_GET,
            'post' => $_POST,
            'files' => $_FILES,
            'cookie' => $_COOKIE,
            'server' => $_SERVER,
            'env' => $_ENV,
        ];
    }

    /**
     * @param array<string, scalar|null> $query
     *
     * @return array<string, string>
     */
    private static function server(string $method, string $path, array $query): array
    {
        $queryString = http_build_query($query);

        return [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path . ($queryString !== '' ? '?' . $queryString : ''),
            'QUERY_STRING' => $queryString,
            'REMOTE_ADDR' => self::remoteAddress(),
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'HTTPS' => 'off',
            'SCRIPT_NAME' => '/bitrix/routing_index.php',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
    }

    private static function remoteAddress(): string
    {
        return sprintf('10.%d.%d.%d', random_int(0, 255), random_int(0, 255), random_int(1, 254));
    }

    private static function application(): HttpApplication
    {
        $application = Application::getInstance();
        if (!$application instanceof HttpApplication) {
            throw new RuntimeException('Bitrix application is not an HTTP application.');
        }

        return $application;
    }

    private static function registerAutoWirings(): void
    {
        if (self::$autoWireRegistered) {
            return;
        }

        Binder::registerGlobalAutoWiredParameter(new Parameter(
            PageNavigation::class,
            static function (): PageNavigation {
                $pageNavigation = new PageNavigation('nav');
                $pageNavigation
                    ->setPageSizes(range(1, 50))
                    ->initFromUri();

                return $pageNavigation;
            }
        ));

        Binder::registerGlobalAutoWiredParameter(new Parameter(
            JsonPayload::class,
            static fn (): JsonPayload => new JsonPayload()
        ));

        Binder::registerGlobalAutoWiredParameter(new Parameter(
            CurrentUser::class,
            static fn (): CurrentUser => CurrentUser::get()
        ));

        self::$autoWireRegistered = true;
    }

    private static function response(mixed $result, Controller $controller): JsonResponse
    {
        $errors = new ErrorCollection();
        $errors->add($controller->getErrors());

        if ($result instanceof HttpResponse) {
            $response = $result;
        } elseif (!$errors->isEmpty()) {
            $response = new AjaxJson($result, AjaxJson::STATUS_ERROR, $errors);
        } else {
            $response = new AjaxJson($result);
        }

        $contextStatus = Context::getCurrent()->getResponse()->getStatus();
        if ($contextStatus !== 200 && $response->getStatus() === 200) {
            $response->setStatus($contextStatus);
        }

        $raw = (string) $response->getContent();
        $decoded = json_decode($raw, true);
        $status = $response->getStatus();

        return new JsonResponse(
            $status === 0 ? 200 : $status,
            is_array($decoded) ? $decoded : [],
            $raw,
        );
    }
}
