<?php
error_reporting(E_ALL | E_STRICT);
// Ensure that composer has installed all dependencies
if (!file_exists(dirname(__DIR__) . '/composer.lock')) {
    die("Dependencies must be installed using composer:\n\nphp composer.phar install --dev\n\n"
        . "See http://getcomposer.org for help with installing composer\n");
}
// Include the composer autoloader
$autoloader = require dirname(__DIR__) . '/vendor/autoload.php';
$autoloader->addPsr4('Ratchet\\', dirname(__DIR__) . '/vendor/cboden/ratchet/tests/helpers/Ratchet');
$autoloader->addPsr4('NH\\', dirname(__DIR__) . '/tests/Unit/LoveLetter');
