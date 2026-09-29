<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Config\Router;
use App\Config\Security;

Security::initSession();

$router = new Router();

require __DIR__ . '/../routes/web.php';
require __DIR__ . '/../routes/api.php';

$router->dispatch(
    parse_url(
        $_SERVER['REQUEST_URI'],
        PHP_URL_PATH
    ),
    $_SERVER['REQUEST_METHOD']
);
?>