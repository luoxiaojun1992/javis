#! /usr/bin/env php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

//Utils
function printLn(...$msg) {
    array_map(function($msg){
        echo $msg, str_repeat(PHP_EOL, 3);
    }, $msg);
}

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
        } elseif ($feedback === -1) {
            return $feedback;
        }
    }
    return null;
}

function optionsDialog($functionsPromotion, $functionHandlers, $root = false) {
    return dialog(function () use ($functionsPromotion, $functionHandlers, $root) {
        printLn($functionsPromotion);

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if ($option === '-1') {
            if ($root) {
                return 3;
            } else {
                return -1;
            }
        }

        if (!ctype_digit($option)) {
            printLn('Invalid option');
            return 1;
        }

        if ($option === '0') {
            if ($root) {
                return 3;
            } else {
                return 2;
            }
        }

        if (!isset($functionHandlers[$option])) {
            printLn('Invalid option');
            return 1;
        }

        $result = call_user_func($functionHandlers[$option]);
        if (($result === -1) && (!$root)) {
            return -1;
        }

        return null;
    });
}

//Entrance
function functionsPrompt() {
    $functionsPromotion = <<<EOF
Please tell me your option:
0. exit
1. zipkin report
2. review bot
3. php repl
4. xxl-job cron exporter
5. github bot
EOF;

    $functionHandlers = [
        '1' => 'zipkinReportHandler',
        '2' => 'reviewBotHandler',
        '3' => 'phpREPLHandler',
        '4' => 'xxlJobExportHandler',
        '5' => 'githubBotHandler',
    ];

    optionsDialog($functionsPromotion, $functionHandlers, true);
}

//Zipkin
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

    return optionsDialog($functionsPromotion, $functionHandlers);
}

function zipkinWeeklyReportHandler() {
    return dialog(function () {
        printLn('Please input args or 0(Exit) or -1(Go to Root)');

        $args = fgets(STDIN);
        $args = rtrim($args, PHP_EOL);

        if ($args === '0') {
            return 2;
        }

        if ($args === '-1') {
            return -1;
        }

        shell_exec('/usr/bin/env php ' . __DIR__ . '/auto-scripts/zipkin/zipkinReport.php ' . $args);

        return null;
    });
}

function zipkinAggReportHandler() {
    return dialog(function () {
        printLn('Please press enter or input 0(Exit) or -1(Go to Root)');

        $args = fgets(STDIN);
        $args = rtrim($args, PHP_EOL);

        if ($args === '0') {
            return 2;
        }

        if ($args === '-1') {
            return -1;
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

//Review Bot
function dirReviewBotHandler() {
    return dialog(function () {
        printLn('Please input dir path or 0(Exit) or -1(Go to Root)');

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if ($option === '0') {
            return 2;
        }

        if ($option === '-1') {
            return -1;
        }

        printLn(json_encode((new \Lxj\Review\Bot\Bot(
            require __DIR__ . '/config/review-robot/analyser.php',
            require __DIR__ . '/config/review-robot/ignored.php'
        ))->review($option)->getErrors(), JSON_PRETTY_PRINT));

        return null;
    });
}

function gitReviewBotHandler() {
    return dialog(function () {
        printLn('Please input merge request url or 0(Exit) or -1(Go to Root)');

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if ($option === '0') {
            return 2;
        }

        if ($option === '-1') {
            return -1;
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

    return optionsDialog($functionsPromotion, $functionHandlers);
}

//REPL
function phpREPLHandler() {
    $_SERVER['argv'] = [];
    $_SERVER['argc'] = 0;
    $argv = [];
    $argc = 0;

    // And go!
    call_user_func(\Psy\bin());
}

//XXL-JOB
function xxlJobExportHandler() {
    return dialog(function () {
        printLn('Please input args or 0(Exit) or -1(Go to Root)');

        $args = fgets(STDIN);
        $args = rtrim($args, PHP_EOL);

        if ($args === '0') {
            return 2;
        }

        if ($args === '-1') {
            return -1;
        }

        shell_exec('/usr/bin/env php ' . __DIR__ . '/auto-scripts/xxl-job/convertToCrontab.php ' . $args);

        return null;
    });
}

//GITHUB Bot
function githubBotHandler() {
    $functionsPromotion = <<<EOF
Please tell me your option:
0. exit
1. issue
2. repo
EOF;

    $functionHandlers = [
        '1' => 'githubBotIssueHandler',
        '2' => 'githubBotRepoHandler',
    ];

    return optionsDialog($functionsPromotion, $functionHandlers);
}

function githubBotIssueHandler() {
    $functionsPromotion = <<<EOF
Please tell me your option:
0. exit
1. issue closer
2. issue report
EOF;

    $functionHandlers = [
        '1' => 'githubBotIssueCloserHandler',
        '2' => 'githubBotIssueReportHandler',
    ];

    return optionsDialog($functionsPromotion, $functionHandlers);
}

function githubBotIssueCloserHandler() {
    return dialog(function () {
        printLn('Please press enter or input 0(Exit) or -1(Go to Root)');

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if ($option === '0') {
            return 2;
        }

        if ($option === '-1') {
            return -1;
        }

        printLn('Closing...');

        printLn(shell_exec('/usr/bin/env php ' . __DIR__ . '/auto-scripts/github/issue/IssueCloser.php'));

        return null;
    });
}

function githubBotIssueReportHandler() {
    return dialog(function () {
        printLn('Please press enter or input 0(Exit) or -1(Go to Root)');

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if ($option === '0') {
            return 2;
        }

        if ($option === '-1') {
            return -1;
        }

        printLn('Generating...');

        printLn(shell_exec('/usr/bin/env php ' . __DIR__ . '/auto-scripts/github/issue/IssueReport.php'));

        return null;
    });
}

function githubBotRepoHandler() {
    return dialog(function () {
        printLn('Please press enter or input 0(Exit) or -1(Go to Root)');

        $option = fgets(STDIN);
        $option = rtrim($option, PHP_EOL);

        if ($option === '0') {
            return 2;
        }

        if ($option === '-1') {
            return -1;
        }

        printLn('Generating...');

        printLn(shell_exec('/usr/bin/env php ' . __DIR__ . '/auto-scripts/github/repo/RepoReporter.php'));

        return null;
    });
}

//Global Config
if (date_default_timezone_get() !== 'PRC') {
    date_default_timezone_set('PRC');
}

//Run
printLn(
        'Hello, I\'m javis.',
    'What can I do for you?'
);

functionsPrompt();
