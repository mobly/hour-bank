<?php

date_default_timezone_set('America/Sao_Paulo');

$basePath = realpath(dirname(__FILE__) . '/../');

require($basePath . '/vendor/autoload.php');

$localConfigurationFile = $basePath . '/configuration/local.php';
if (!file_exists($localConfigurationFile)) {
    exit('Please copy the local.php.template to local.php and configure with your data');
}

$configuration = SimpleHelpers\ArrayHelper::configuration([
    $basePath . '/configuration/application.php',
    $localConfigurationFile,
]);
