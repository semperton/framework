<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Semperton\Container\Container;

// set some php system defaults
date_default_timezone_set('UTC');

/** @var ClassLoader */
$classLoader = require_once __DIR__ . '/../vendor/autoload.php';


// load container
$definitions = require __DIR__ . '/definitions.php';
$container = new Container($definitions, false);


return $container;