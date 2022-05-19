<?php

declare(strict_types=1);

use Phplease\Foundation\Application;
use Phplease\Http\Kernel;

defined('ROOT') or die('ROOT constant not defined');
defined('WRITABLE') or die('WRITABLE constant not defined');

/*
|-----------------------------------------------------
| Register The Auto Loader
|-----------------------------------------------------
|
| Simply register the autloader function so that we
| don't have to worry about manual loading any of our
| classes later on. It feels great to relax.
|
*/

include ROOT . '/vendor/autoload.php';

/*
|-----------------------------------------------------
| Bind Important Interfaces
|-----------------------------------------------------
|
| Next, we need to bind some important interfaces into
| the provider so we will be able to resolve them
| when needed.
|
*/

const CONFIG_DIR = ROOT . '/var/config';

$app = new Application();

$app->provideMultiple([
    'cache' => fn () => new \Phplease\Caching\FileStorage(WRITABLE . '/cache'),
    'config' => new \Phplease\Config\PhpAdapter(),
    'kernel' => new Kernel(),
    'router' => new \Phplease\Routing\Router(),
    'db' => function () {
        $config = Application::getInstance()->providerOf('config')->load(CONFIG_DIR . '/phplease.php');
        $db = new \Phplease\Database\Connection(...$config['database']);

        return $db;
    },
    'view' => function () {
        /** @var \Phplease\Config\IAdapter */
        $config_provider = Application::getInstance()->providerOf('config');
        $config = $config_provider->load(CONFIG_DIR . '/phplease.php');

        $engine = new \Signal\Compiler(ROOT . '/var/themes');
        $engine->setDebug('production' !== $config['mode']);
        $engine->setCacheDirectory(WRITABLE . '/themes');

        $directives = $config_provider->load(CONFIG_DIR . '/directives.php');
        foreach ($directives as $name => $callback) {
            $engine->registerDirective($name, $callback);
        }

        return new \Signal\View($engine);
    }
]);

/*
|-----------------------------------------------------
| Load configuration file
|-----------------------------------------------------
|
| Load and parse the main configuration file.
| PHP files are used as its supported configuration file
| type. The site configuration file is phplease.php the
| rest is loaded as needed and managed by packages.
|
*/

$config_provider = $app->providerOf('config');

$config = $config_provider->load(CONFIG_DIR . '/phplease.php');

date_default_timezone_set($config['timezone'] ?? 'Europe/Lisbon');

if (! defined('SID') && ($config['session_name'] ?? false)) {
    session_name($config['session_name'] ?? 'patife');
    session_cache_expire($config['session_expires'] ?? 30);
    session_save_path(WRITABLE . '/sessions');
    session_start();
}

/*
|-----------------------------------------------------
| Bind Custom Interfaces
|-----------------------------------------------------
|
| Next, we need to bind user interfaces into the
| provider so we will be able to resolve them when
| needed.
|
*/

$app->provideMultiple($config_provider->load(CONFIG_DIR . '/providers.php'));

/*
|-----------------------------------------------------
| Load and setup routes
|-----------------------------------------------------
|
| Routes handle incoming requests. The default
| routes are loaded from the configuration file
| but packages can also register and handle routes.
|
*/

$kernel_provider = $app->providerOf('kernel');
$route_provider = $app->providerOf('router');

$route_provider->addRoutes($config_provider->load(CONFIG_DIR . '/routes.php'));
$kernel_provider->register($route_provider, Kernel::PRIORITY_LOW);

$middleware = $config_provider->load(CONFIG_DIR . '/middleware.php');
foreach ($middleware as $item) {
    $kernel_provider->register($item);
}

unset($config, $middleware, $config_provider, $kernel_provider, $route_provider);

return $app;
