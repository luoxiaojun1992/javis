#! /usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

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

function functionsPrompt() {
    dialog(function () {
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

        printLn($functionsPromotion);

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if (!ctype_digit($option)) {
            printLn('Invalid option');
            return 1;
        }

        if ($option === '0') {
            return 3;
        }

        if (!isset($functionHandlers[$option])) {
            printLn('Invalid option');
            return 1;
        }

        call_user_func($functionHandlers[$option]);

        return null;
    });
}

function zipkinReportHandler() {
    printLn('Please input args');

    $args = fgets(STDIN);

    shell_exec('/usr/bin/env php ' . __DIR__ . '/auto-scripts/zipkinReport.php ' . $args);
}

function dirReviewBotHandler() {
    dialog(function () {
        printLn('Please input dir path');

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if ($option === '0') {
            return 2;
        }

        if (!is_string($option)) {
            printLn('Invalid option');
            return 1;
        }

        if ((!is_dir($option)) && (!file_exists($option))) {
            printLn('Invalid option');
            return 1;
        }

        printLn(json_encode((new \Lxj\Review\Bot\Bot(
            require __DIR__ . '/config/analyser.php',
            require __DIR__ . '/config/ignored.php'
        ))->review($option)->getErrors(), JSON_PRETTY_PRINT));

        return null;
    });
}

function gitReviewBotHandler() {
    dialog(function () {
        printLn('Please input merge request url');

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if ($option === '0') {
            return 2;
        }

        if (!is_string($option)) {
            printLn('Invalid option');
            return 1;
        }

        printLn(json_encode((new \Lxj\Review\Bot\GitBot(
            new \Lxj\Review\Bot\Bot(
                require __DIR__ . '/config/analyser.php',
                require __DIR__ . '/config/ignored.php'
            ),
            require __DIR__ . '/config/gitlab.php'
        ))->review($option)->getErrors(), JSON_PRETTY_PRINT));

        return null;
    });
}

function reviewBotHandler() {
    dialog(function () {
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

        printLn($functionsPromotion);

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if (!ctype_digit($option)) {
            printLn('Invalid option');
            return 1;
        }

        if ($option === '0') {
            return 2;
        }

        if (!isset($functionHandlers[$option])) {
            printLn('Invalid option');
            return 1;
        }

        call_user_func($functionHandlers[$option]);

        return null;
    });
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
