#! /usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

if (date_default_timezone_get() !== 'PRC') {
    date_default_timezone_set('PRC');
}

function printLn(...$msg) {
    array_map(function($msg){
        echo $msg, str_repeat(PHP_EOL, 3);
    }, $msg);
}

printLn(
        'Hello, I\'m javis.',
    'What can I do for you?'
);

function dialog($chatBot) {
    while (true) {
        $feedback = call_user_func($chatBot);
        if ($feedback === 1) {
            continue;
        } elseif ($feedback === 2) {
            break;
        } elseif ($feedback === 3) {
            exit(0);
        } elseif ($feedback === 4) {
            exit(1);
        }
    }
}

function optionsDialog($functionsPromotion, $functionHandlers, $outer = false) {
    dialog(function () use ($functionsPromotion, $functionHandlers, $outer) {
        printLn($functionsPromotion);

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if (!ctype_digit($option)) {
            printLn('Invalid option');
            return 1;
        }

        if ($option === '0') {
            if ($outer) {
                return 3;
            } else {
                return 2;
            }
        }

        if (!isset($functionHandlers[$option])) {
            printLn('Invalid option');
            return 1;
        }

        call_user_func($functionHandlers[$option]);

        return null;
    });
}

function functionsPrompt() {
    $functionsPromotion = <<<EOF
Please tell me your option:
0. exit
1. zipkin report
2. review bot
3. php repl
EOF;

    $functionHandlers = [
        '1' => 'zipkinReportHandler',
        '2' => 'reviewBotHandler',
        '3' => 'phpREPLHandler',
    ];

    optionsDialog($functionsPromotion, $functionHandlers, true);
}

function zipkinReportHandler() {
    $functionsPromotion = <<<EOF
Please tell me your option:
0. exit
1. weekly report
2. agg report
EOF;

    $functionHandlers = [
        '1' => 'zipkinWeeklyReportHandler',
        '2' => 'zipkinAggReportHandler',
    ];

    optionsDialog($functionsPromotion, $functionHandlers);
}

function zipkinWeeklyReportHandler() {
    dialog(function () {
        printLn('Please input args or 0(Exit)');

        $args = fgets(STDIN);
        $args = rtrim($args, PHP_EOL);

        if ($args === '0') {
            return 2;
        }

        shell_exec('/usr/bin/env php ' . __DIR__ . '/auto-scripts/zipkin/zipkinReport.php ' . $args);

        return null;
    });
}

function zipkinAggReportHandler() {
    dialog(function () {
        printLn('Please press enter or input 0(Exit)');

        $args = fgets(STDIN);
        $args = rtrim($args, PHP_EOL);

        if ($args === '0') {
            return 2;
        }

        printLn('Generating agg report...');

        shell_exec('/usr/bin/env php ' . __DIR__ . '/auto-scripts/zipkin/aggReport.php');

        printLn('Generating diagram...');

        shell_exec(
            '/usr/bin/env python ' . __DIR__ . '/auto-scripts/zipkin/visualizer.py ' .
            __DIR__ . '/auto-scripts/zipkin/output/zipkin-agg-report-' . date('Y-m-d') . '.csv'
        );

        return null;
    });
}

function dirReviewBotHandler() {
    dialog(function () {
        printLn('Please input dir path or 0(Exit)');

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if ($option === '0') {
            return 2;
        }

        printLn(json_encode((new \Lxj\Review\Bot\Bot(
            require __DIR__ . '/config/review-robot/analyser.php',
            require __DIR__ . '/config/review-robot/ignored.php'
        ))->review($option)->getErrors(), JSON_PRETTY_PRINT));

        return null;
    });
}

function gitReviewBotHandler() {
    dialog(function () {
        printLn('Please input merge request url or 0(Exit)');

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if ($option === '0') {
            return 2;
        }

        printLn(json_encode((new \Lxj\Review\Bot\GitBot(
            new \Lxj\Review\Bot\Bot(
                require __DIR__ . '/config/review-robot/analyser.php',
                require __DIR__ . '/config/review-robot/ignored.php'
            ),
            require __DIR__ . '/config/review-robot/gitlab.php'
        ))->review($option)->getErrors(), JSON_PRETTY_PRINT));

        return null;
    });
}

function reviewBotHandler() {
    $functionsPromotion = <<<EOF
Please tell me your option:
0. exit
1. dir review bot
2. git review bot
EOF;

    $functionHandlers = [
        '1' => 'dirReviewBotHandler',
        '2' => 'gitReviewBotHandler',
    ];

    optionsDialog($functionsPromotion, $functionHandlers);
}

function phpREPLHandler() {
    $_SERVER['argv'] = [];
    $_SERVER['argc'] = 0;
    $argv = [];
    $argc = 0;

    // And go!
    call_user_func(\Psy\bin());
}

functionsPrompt();
