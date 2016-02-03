#!/usr/bin/env php
<?php
// amp-console.php

require __DIR__.'/vendor/autoload.php';

use Lullabot\AMP\AmpCommand;
use Symfony\Component\Console\Application;

$application = new Application();
$application->add(new AmpCommand());
$application->run();
