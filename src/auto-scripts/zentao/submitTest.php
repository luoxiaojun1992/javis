<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

if (date_default_timezone_get() != 'Asia/Shanghai') {
    date_default_timezone_set('Asia/Shanghai');
}

$jar = new \GuzzleHttp\Cookie\CookieJar;
$client = new \GuzzleHttp\Client();

$zentaoConfig = require_once __DIR__ . '/config/zentao.php';
$zentaoHost = $zentaoConfig['host'];

$client->get(
    $zentaoHost . '/index.php?m=user&f=login',
    [
        'cookies' => $jar,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
        ]
    ]
);

$response = $client->get(
    $zentaoHost . '/index.php?m=user&f=refreshRandom&t=html',
    [
        'cookies' => $jar,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
            'Referer' => 'https://zentao.jingsocial.com/index.php?m=user&f=login',
        ]
    ]
);
$rand = $response->getBody()->getContents();

$accountConfig = require_once __DIR__ . '/config/account.php';

$client->post(
    $zentaoHost . '/index.php?m=user&f=login&t=html',
    [
        'cookies' => $jar,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
        ],
        'form_params' => [
            'account' => $accountConfig['account'],
            'password' => md5((md5($accountConfig['password']) . $rand)),
            'passwordStrength' => 1,
            'referer' => '/',
            'verifyRand' => intval($rand),
            'keepLogin' => 1,
            'captcha' => '',
        ]
    ]
);

$product = intval($argv[1]);
$build = intval($argv[2]);
$owner = $argv[3];

$testConfig = require_once __DIR__ . '/config/test.php';

$extraParams = $testConfig['extra_params'];

$submitTest = function ($task) use ($client, $jar, $zentaoHost, $product, $build, $owner, $extraParams) {
    $response = $client->get(
        $zentaoHost . '/index.php?m=testtask&f=create&product=' . ((string)$product),
        [
            'cookies' => $jar,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
                'Referer' => 'https://zentao.jingsocial.com/index.php?m=user&f=login',
            ]
        ]
    );

    $createTestHtml = $response->getBody()->getContents();

    if (!preg_match('/var kuid = \'([0-9a-zA-Z]+)\'/', $createTestHtml, $matches)) {
        echo 'Kuid not matched', PHP_EOL;
        exit(1);
    }

    if (count($matches) > 2) {
        echo 'Invalid Kuid', PHP_EOL;
        print_r($matches);
        echo PHP_EOL;
        exit(1);
    }

    $kuid = $matches[1];

    $response = $client->post(
        $zentaoHost . '/index.php?m=testtask&f=create&product=' . ((string)$product),
        [
            'cookies' => $jar,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
            ],
            'form_params' => array_merge([
                'product' => $product,
                'execution' => $task['task_execution'],
                'build' => $build,
                'abouttask' => $task['task_id'],
                'owner' => $owner,
                'pri' => 0,
                'begin' => date('Y-m-d'),
                'end' => date('Y-m-d', strtotime('+1 day')),
                'status' => 'wait',
                'name' => $task['task_title'],
                'desc' => '',
                'mailto' => [],
                'uid' => $kuid,
            ], $extraParams)
        ]
    );

    var_dump($response->getBody()->getContents());
};

$taskIdListStr = $argv[4];
if (strlen($taskIdListStr) === 0) {
    echo 'Invalid task list', PHP_EOL;
    exit(1);
}
$taskIdList = explode(',', $taskIdListStr);
array_walk($taskIdList, function (&$taskId) {$taskId = intval($taskId);});

$response = $client->get(
    $zentaoHost . '/index.php?m=my&f=work&mode=task',
    [
        'cookies' => $jar,
        'headers' => [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
        ],
    ]
);

$taskListHtml = $response->getBody()->getContents();

$taskList = [];
/** @var \simple_html_dom\simple_html_dom $taskPageDom */
$taskPageDom = \KubAT\PhpSimple\HtmlDomParser::str_get_html($taskListHtml);
$taskTableDom = $taskPageDom->getElementById('taskTable');
$taskListDom = $taskTableDom ? $taskTableDom->children(1) : null;
$taskListDom = $taskListDom ? $taskListDom->children : [];
foreach ($taskListDom as $taskDom) {
    $taskTitle = null;
    $taskExecution = null;
    foreach ($taskDom->children as $taskDomChild) {
        if ($taskDomChild->getAttribute('class') === 'c-name') {
            $taskTitle = $taskDomChild->getAttribute('title');
        }
        if ($taskDomChild->getAttribute('class') === 'c-project') {
            foreach ($taskDomChild->children as $taskProjectDomChild) {
                if ($taskProjectDomChild->getAttribute('data-group') === 'execution') {
                    if ($executionLink = $taskProjectDomChild->getAttribute('href')) {
                        $urlInfo = (parse_url($executionLink));
                        if (isset($urlInfo['query'])) {
                            parse_str($urlInfo['query'], $queryParams);
                            if (($queryParams['m'] === 'execution') && ($queryParams['f'] === 'task')) {
                                $taskExecution = intval($queryParams['executionID']);
                            }
                        }
                    }
                }
            }
        }
    }
    $taskList[] = [
        'task_id' => intval($taskDom->getAttribute('data-id')),
        'task_status' => $taskDom->getAttribute('data-status'),
        'task_est' => intval($taskDom->getAttribute('data-estimate')),
        'task_consumed' => intval($taskDom->getAttribute('data-consumed')),
        'task_left' => intval($taskDom->getAttribute('data-left')),
        'task_title' => $taskTitle,
        'task_execution' => $taskExecution,
    ];
}

$filteredTaskList = [];
foreach ($taskList as $task) {
    if (!in_array($task['task_id'], $taskIdList)) {
        continue;
    }
    if (is_null($task['task_title'])) {
        continue;
    }
    if (is_null($task['task_execution'])) {
        continue;
    }
    $filteredTaskList[] = $task;
}

foreach ($filteredTaskList as $task) {
    $submitTest($task);
}
