<?php

if (empty($argv[1])) {
    echo sprintf(
        'Input the tasks%sUsage: php %s %s',
        PHP_EOL,
        $argv[0],
        'ST-1234,ST-4321'
    );
    die();
}
$tasks = explode(',', str_replace('ST-', '', $argv[1]));
$month = !empty($argv[2]) ? $argv[2] : intval(date('m'));
$year = !empty($argv[3]) ? $argv[3] : intval(date('Y'));

$days = [];
foreach (range(1, 31) as $day) {
    $time = mktime(0, 0, 0, $month, $day, $year);
    if (intval(date('m', $time)) == $month && !in_array(date('w', $time), [6, 0])) {
       $days[] = date('d/m', $time);
    }
}

$daysCount = count($days);
$tasksCount = count($tasks);
$daysPerTask = array_chunk($days, $daysCount / $tasksCount);

$daysAndTasks = [];

foreach ($tasks as $key => $task) {
    $daysAndTasks[$task] = $daysPerTask[$key];
    unset($daysPerTask[$key]);
}

if (!empty($daysPerTask)) {
    $task = key($daysAndTasks);
    $daysAndTasks[$task] = array_merge($daysAndTasks[$task], array_shift($daysPerTask));
}

foreach ($daysAndTasks as $task => $days) {
    foreach ($days as $day) {
        echo sprintf(
            'php src/log.php %d %s 8h00 Correção de Bugs, Deploy, Gestão do Time, Análises;%s',
            $task,
            $day,
            PHP_EOL
        );
    }
}

//print_r($days);
//print_r($tasks);
//print_r($daysAndTasks);
//print_r($daysPerTask);
