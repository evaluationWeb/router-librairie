<?php

require __DIR__ . '/../vendor/autoload.php';

use Mithridatem\Routing\Router;
use Mithridatem\Routing\Route;

$router = new Router();

$router->map(Route::get('/', function () {
    echo "Welcome to Mithridatem Router";
}));

$router->dispatch();
