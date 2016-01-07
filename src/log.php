<?php

use SimpleHelpers\Cli;
use SimpleHelpers\String;

$help = '
/**
 * to see status:
 *      $ php src/log.php [--date={dd/mm[/yy]}]
 *
 * ####
 *
 * to see report:
 *      $ php src/log.php --report [--date={dd/mm[/yy]}]
 *
 * ####
 *
 * to start:
 *      $ php src/log.php {MEBLO #}
 * Ex.:
 *      $ php src/log.php 1111
 *
 * ####
 *
 * to stop:
 *      $ php src/log.php {MEBLO #} [{message}]
 * Ex.:
 *      $ php src/log.php 1111
 *      $ php src/log.php 1111 added new feature x
 *
 * ####
 *
 * to log a period:
 *      $ php src/log.php {MEBLO #} [{dd/mm[/yy]}] {00h00} [{message}]
 * Ex.:
 *      $ php src/log.php 1111 2h15
 *      $ php src/log.php 1111 2h15 improved feature z
 *      $ php src/log.php 1111 31/12 2h15 improved feature z
 *
 * ####
 *
 * to edit:
 *      $ php src/log.php --edit={MEBLO #} --index={index #} [--start={00h00}] [--stop={00h00}] [--comment="{message string}"]
 * Ex.:
 *      $ php src/log.php --edit=1111 --index=1 --start=08h12
 *      $ php src/log.php --edit=1111 --index=1 --stop=08h42
 *      $ php src/log.php --edit=1111 --index=1 --comment="added new adapter"
 *      $ php src/log.php --edit=1111 --index=1 --start=08h12 --stop=08h42
 *      $ php src/log.php --edit=1111 --index=1 --start=08h12 --comment="fixed bug"
 *      $ php src/log.php --edit=1111 --index=1 --stop=08h42 --comment="removed unused code"
 *      $ php src/log.php --edit=1111 --index=1 --start=08h12 --stop=08h42 --comment="refactoring y"
 *
 * ** see the index # next to MEBLO in status command
 *
 * ####
 *
 * to remove:
 *      $ php src/log.php --delete={MEBLO #} --index={index #}
 * Ex.:
 *      $ php src/log.php --delete=1111 --index=1
 *
 * ** see the index # next to MEBLO in status command
 *
 * ####
 *
 * @author Caio Costa <caio.costa@mobly.com.br>
 * @since 14/10/2015
 * @version 07/01/2016
 */';

require('common.php');

$optionList = getopt('h::', [
    'help::', 'delete::', 'edit::', 'report::',
    'index::', 'start::', 'stop::', 'comment::', 'date::'
]);
$linkPrefix = $configuration['jiraEndpoint'];
$command = 'status';
$file = $basePath . '/data/hour-log.json';
$now = new DateTime();
$dateMySQLFormat = 'Y-m-d';
$dateDayFormat = 'd';
$dateDayMonthFormat = $dateDayFormat . '/m';
$dateDayMonthYearFormat = $dateDayMonthFormat . '/y';
$timeFormat = 'H\hi';
$time = $now->format($timeFormat);
$intervalFormat = '%Hh%I';
$data = [];

$date = $now->format($dateMySQLFormat);
if (isset($optionList['date'])) {
    $datePartList = explode('/', $optionList['date']);
    $dateFormat = $dateDayFormat;

    switch (count($datePartList)) {
        case 3:
            $dateFormat = $dateDayMonthYearFormat;
            break;
        case 2:
            $dateFormat = $dateDayMonthFormat;
            break;
    }

    $date = DateTime::createFromFormat($dateFormat, $optionList['date']);

    if (false === $date) {
        $error = DateTime::getLastErrors();

        Cli::writeOutput(
            'Date error(s): ' . implode(', ', array_merge($error['warnings'], $error['errors']))
                . String::newLine(2),
            Cli::COLOR_RED_BOLD
        );

        exit;
    }

    $date = $date->format($dateMySQLFormat);
}

if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);

    if (!is_array($data)) {
        Cli::writeOutput('Unexpected data' . String::newLine(2), Cli::COLOR_RED_BOLD);

        exit;
    }
}

if (!isset($data[$date])) {
    $data[$date] = [];
}

if (isset($argc) && $argc > 1 && !isset($optionList['date'])) {
    $command = $argv[1];
}
if (isset($optionList['edit'])) {
    $command = 'edit';
}
if (isset($optionList['delete'])) {
    $command = 'delete';
}
if (isset($optionList['report'])) {
    $command = 'report';
}
if (isset($optionList['help']) || isset($optionList['h'])) {
    $command = 'help';
}

switch ($command)
{
    case 'help':
        Cli::writeOutput(trim($help) . String::newLine());
        break;

    case 'status':
        $total = new DateTime("@0");
        foreach ((array) $data[$date] as $key => $entryList) {
            $form = '';
            $outputList = [
                [
                    'message' => $linkPrefix . $key,
                    'color' => Cli::COLOR_WHITE,
                ]
            ];
            foreach ($entryList as $index => $entry) {
                if (isset($entry['form'])) {
                    $form = $entry['form'] . String::newLine();

                    continue;
                }

                $color = Cli::COLOR_YELLOW;
                $running = true;
                $end = $now;

                if (!empty($entry['stop'])) {
                    $color = '';
                    $running = false;
                    $end = DateTime::createFromFormat($timeFormat, $entry['stop']);
                }

                $duration = DateTime::createFromFormat($timeFormat, $entry['start'])->diff($end);

                $message = '#' . $index . ' '
                    . $duration->format($intervalFormat)
                    //. ' (' . number_format($duration->h + ($duration->i / 60), 2) . ')'
                ;

                if (!empty($entry['comment'])) {
                    $message .= ' ' . $entry['comment'];
                }
                if ($running) {
                    $message .= ' *RUNNING*';
                }

                $outputList[] = [
                    'message' => $message,
                    'color' => $color,
                ];

                $total->add($duration);
            }

            foreach ($outputList as $output) {
                Cli::writeOutput(
                    $output['message'],
                    empty($form) ? $output['color'] : Cli::COLOR_WHITE_DIM
                );
            }

            Cli::writeOutput(
                $form,
                empty($form) ? Cli::COLOR_WHITE : Cli::COLOR_WHITE_DIM
            );
        }

        $dayTotal = new DateTime('@' . (8 * 60 * 60));
        $doneInterval = $dayTotal->diff($total);

        $hasDifference = ($doneInterval->h + $doneInterval->i) > 0 && $doneInterval->invert;

        Cli::writeOutput(
            'Worked ' . $total->format($timeFormat),
            ($hasDifference ? Cli::COLOR_GREEN : Cli::COLOR_GREEN_BOLD)
        );

        if ($hasDifference) {
            Cli::writeOutput(
                'Remaining ' . $total->diff($dayTotal)->format($intervalFormat),
                Cli::COLOR_YELLOW
            );
        }

        Cli::writeOutput(String::newLine());
        break;

    case 'report':
        foreach ((array) $data[$date] as $key => $entryList) {
            if (empty($entryList)) {
                continue;
            }

            $commentList = [];
            $total = new DateTime("@0");
            foreach ($entryList as $index => $entry) {
                if (empty($entry['stop'])) {
                    continue;
                }

                $total->add(
                    DateTime::createFromFormat($timeFormat, $entry['start'])
                        ->diff(
                            DateTime::createFromFormat($timeFormat, $entry['stop'])
                        )
                );

                if (!empty($entry['comment'])) {
                    $comment = str_replace('\n', String::newLine(), $entry['comment']);

                    $commentList[$comment] = $comment;
                }
            }

            $message = $key . ' '
                . number_format(
                    (int) $total->format('H') + ((int) $total->format('i') / 60),
                    2
                ) . String::newLine() . implode(String::newLine(), $commentList);
            ;

            Cli::writeOutput($message . String::newLine());
        }

        Cli::writeOutput(String::newLine());
        break;

    case 'edit':
        $key = $optionList['edit'];

        if (!String::validateNumber($key)) {
            Cli::writeOutput(
                'MEBLO must be a integer and length must be greater than 3' . String::newLine(2),
                Cli::COLOR_RED_BOLD
            );

            exit;
        }

        $index = (int) $optionList['index'];

        if (empty($data[$date][$key][$index]['start'])) {
            Cli::writeOutput(
                $key . ' #' . $index . ' not found!' . String::newLine(2),
                Cli::COLOR_RED_BOLD
            );

            exit;
        }

        $entry = &$data[$date][$key][$index];

        if (isset($optionList['start'])) {
            $startTime = DateTime::createFromFormat($timeFormat, $optionList['start']);

            if (false === $startTime) {
                $error = DateTime::getLastErrors();

                Cli::writeOutput(
                    'Start error(s): ' . implode(', ', array_merge($error['warnings'], $error['errors']))
                        . String::newLine(2),
                    Cli::COLOR_RED_BOLD
                );

                exit;
            }

            $entry['start'] = $startTime->format($timeFormat);
        }

        if (isset($optionList['stop'])) {
            $stopTime = DateTime::createFromFormat($timeFormat, $optionList['stop']);

            if (false === $stopTime) {
                $error = DateTime::getLastErrors();

                Cli::writeOutput(
                    'Stop error(s): ' . implode(', ', array_merge($error['warnings'], $error['errors']))
                        . String::newLine(2),
                    Cli::COLOR_RED_BOLD
                );

                exit;
            }

            $entry['stop'] = $stopTime->format($timeFormat);
        }

        if (isset($optionList['comment'])) {
            $entry['comment'] = $optionList['comment'];
        }

        Cli::writeOutput(
            $key . ' #' . $index . ' edited' . String::newLine(2),
            Cli::COLOR_YELLOW_BOLD
        );
        break;

    case 'delete':
        $key = $optionList['delete'];

        if (!String::validateNumber($key)) {
            Cli::writeOutput(
                'MEBLO must be a integer and length must be greater than 3' . String::newLine(2),
                Cli::COLOR_RED_BOLD
            );

            exit;
        }

        $index = (int) $optionList['index'];

        if (!isset($data[$date][$key][$index])) {
            Cli::writeOutput(
                $key . ' #' . $index . ' not found!' . String::newLine(2),
                Cli::COLOR_RED_BOLD
            );

            exit;
        }

        unset($data[$date][$key][$index]);

        Cli::writeOutput(
            $key . ' #' . $index . ' removed' . String::newLine(2),
            Cli::COLOR_RED_BOLD
        );
        break;

    default:
        $key = $command;

        if (!String::validateNumber($key)) {
            Cli::writeOutput(
                'MEBLO must be a integer and length must be greater than 3' . String::newLine(2),
                Cli::COLOR_RED_BOLD
            );

            exit;
        }

        $argvIndex = 2;

        if (isset($argv[$argvIndex])) {
            $datePartList = explode('/', $argv[$argvIndex]);
            $dateFormat = $dateDayFormat;

            switch (count($datePartList)) {
                case 3:
                    $dateFormat = $dateDayMonthYearFormat;
                    break;
                case 2:
                    $dateFormat = $dateDayMonthFormat;
                    break;
            }

            $possibleDate = DateTime::createFromFormat($dateFormat, $argv[$argvIndex]);

            if ($possibleDate instanceof DateTime) {
                $date = $possibleDate->format($dateMySQLFormat);

                $argvIndex++;
            }
        }

        if (!isset($data[$date][$key])) {
            $data[$date][$key] = [];
        }

        $dataTodayKey = &$data[$date][$key];

        if (isset($argv[$argvIndex])) {
            $possibleTime = DateTime::createFromFormat($timeFormat, $argv[$argvIndex]);

            if ($possibleTime instanceof DateTime) {
                $argvIndex++;

                $dataTodayKey[] = [
                    'start' => '00h00',
                    'stop' => $possibleTime->format($timeFormat),
                    'comment' => implode(' ', array_slice($argv, $argvIndex))
                ];

                Cli::writeOutput(
                    $key . ' added' . String::newLine(2),
                    Cli::COLOR_GREEN_BOLD
                );

                break;
            }
        }

        $count = count($dataTodayKey);
        $index = $count - 1;

        if ($count > 0 && !isset($dataTodayKey[$index]['stop'])) {
            $entry = &$dataTodayKey[$index];
            $entry['stop'] = $time;
            $entry['comment'] = implode(' ', array_slice($argv, 2));

            $duration = DateTime::createFromFormat($timeFormat, $entry['start'])
                ->diff($now)
                ->format($intervalFormat)
            ;

            Cli::writeOutput(
                $key . ' stopped with ' . $duration . String::newLine(2),
                Cli::COLOR_GREEN_BOLD
            );
        } else {
            $dataTodayKey[] = [
                'start' => $time,
            ];

            Cli::writeOutput(
                $key . ' started' . String::newLine(2),
                Cli::COLOR_GREEN_BOLD
            );
        }
}

file_put_contents(
    $file,
    json_encode(
        $data,
        JSON_PRETTY_PRINT
    )
);