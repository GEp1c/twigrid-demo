<?php

require_once __DIR__ . '/libs/autoload.php';

$c = new Nette\Config\Configurator;
$c->setTempDirectory( __DIR__ . '/temp' );
$c->enableDebugger( __DIR__ . '/log' );
$c->createRobotLoader()->addDirectory( array( __DIR__ . '/app' ) )->register();
$c->addConfig( __DIR__ . '/app/config.neon', $c::NONE );

$container = $c->createContainer();
$container->router[] = new Nette\Application\Routers\SimpleRouter('Homepage:default');
$container->application->run();
