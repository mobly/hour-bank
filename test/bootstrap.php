<?php

use SimpleHelpers\Cli;
use SimpleHelpers\String;

declare(ticks = 1);

$basePath = realpath(dirname(__FILE__) . '/../');

require($basePath . '/src/common.php');

$os = PHP_OS == 'Darwin' ? 'mac' : 'linux';
$showSeleniumDebug = false;

if (file_exists($basePath . '/vendor/chromedriver/chromedriver-' . $os . '/chromedriver')) {
    $seleniumDebug = $showSeleniumDebug ? '' : ' 2>&1';

    Cli::writeOutput('Starting Selenium' . String::newLine(2));

    $exec = exec(
        'java -Dwebdriver.chrome.driver=' . $basePath . '/vendor/chromedriver/chromedriver-' . $os . '/chromedriver -jar '
        . $basePath . '/vendor/seleniumhq/selenium-server-standalone/selenium-server-standalone-2.48.2.jar > /dev/null ' . $seleniumDebug . ' &',
        $output,
        $return
    );

    sleep(2);

    $shutdown = function() {
        Cli::writeOutput('Stopping Selenium' . String::newLine(2));

        $pid = Cli::execute('ps aux | grep selenium | grep -v grep | awk \'{ print $2 }\' "$@"');

        Cli::execute('kill -9 ' . $pid['return']);

        Cli::writeOutput('Done!' . String::newLine());

        exit;
    };

    register_shutdown_function($shutdown);

    // above not working yet :(
    pcntl_signal(SIGINT, $shutdown);
    pcntl_signal(SIGTERM, $shutdown);
    pcntl_signal(SIGUSR1, $shutdown);
    pcntl_signal(SIGQUIT, $shutdown);
}