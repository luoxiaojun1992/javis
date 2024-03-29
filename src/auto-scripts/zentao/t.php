<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

if (date_default_timezone_get() != 'Asia/Shanghai') {
    date_default_timezone_set('Asia/Shanghai');
}

$jar = new \GuzzleHttp\Cookie\CookieJar;
$client = new \GuzzleHttp\Client();

$response = $client->get('http://tool.bitefu.net/jiari/?d=' . date('Y-m-d'));
$flag = intval($response->getBody()->getContents());
if (in_array($flag, [1, 2])) {
    echo 'Today is a holiday', PHP_EOL;
    exit(0);
}

$holidaysConfig = require_once __DIR__ . '/config/holidays.php';
$holidays = $holidaysConfig['holidays'];
foreach ($holidays as $holiday) {
    if (is_array($holiday)) {
        $nowTime = time();
        if ((strtotime($holiday[0]) <= $nowTime) && (strtotime($holiday[1]) >= $nowTime)) {
            echo 'Today is a holiday', PHP_EOL;
            exit(0);
        }
    } else {
        if ($holiday === date('Y-m-d')) {
            echo 'Today is a holiday', PHP_EOL;
            exit(0);
        }
    }
}

mt_srand(time());
sleep(mt_rand(0, 300));

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

sleep(mt_rand(30, 120));

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
$taskPageDom = \KubAT\PhpSimple\HtmlDomParser::str_get_html($taskListHtml);
$taskTableDom = $taskPageDom->getElementById('taskTable');
$taskListDom = $taskTableDom ? $taskTableDom->children(1) : null;
$taskListDom = $taskListDom ? $taskListDom->children : [];
foreach ($taskListDom as $taskDom) {
    $taskList[] = [
        'task_id' => intval($taskDom->getAttribute('data-id')),
        'task_status' => $taskDom->getAttribute('data-status'),
        'task_est' => intval($taskDom->getAttribute('data-estimate')),
        'task_consumed' => intval($taskDom->getAttribute('data-consumed')),
        'task_left' => intval($taskDom->getAttribute('data-left')),
    ];
}

$setWorkingTime = function($taskId, $date, $consumed, $left) use ($client, $jar, $zentaoHost)
{
    $response = $client->get(
        $zentaoHost . '/index.php?m=task&f=recordEstimate&taskID='.$taskId.'&onlybody=yes',
        [
            'cookies' => $jar,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
            ],
        ]
    );

    $response = $client->post(
        $zentaoHost . '/index.php?m=task&f=recordEstimate&taskID='.$taskId.'&onlybody=yes',
        [
            'cookies' => $jar,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.107 Safari/537.36',
            ],
            'form_params' => [
                'id' => [
                    1 => 1,
                    2 => 2,
                    3 => 3,
                ],
                'dates' => [
                    1 => $date,
                    2 => $date,
                    3 => $date,
                ],
                'consumed' => [
                    1 => $consumed,
                    2 => '',
                    3 => '',
                ],
                'left' => [
                    1 => $left,
                    2 => '',
                    3 => '',
                ],
                'work' => [
                    1 => '',
                    2 => '',
                    3 => '',
                ]
            ]
        ]
    );

    var_dump($response->getBody()->getContents());
};

$taskConfig = require_once __DIR__ . '/config/task.php';

$workingTaskIds = $taskConfig['working_task_ids'];

$filteredTaskList = [];
foreach ($taskList as $task) {
    if ($task['task_status'] !== 'doing') {
        continue;
    }
    if ($task['task_left'] <= 0) {
        continue;
    }
    if (!in_array($task['task_id'], $workingTaskIds)) {
        continue;
    }
    $filteredTaskList[] = $task;
}

$taskLeftList = array_column($filteredTaskList, 'task_left');
array_multisort($taskLeftList, SORT_ASC, $filteredTaskList);

$estWorkingTime = $taskConfig['est_working_time'];
foreach ($filteredTaskList as $i => $task) {
    $estConsumed = $task['task_left'];
    if ($estWorkingTime < $estConsumed) {
        $estConsumed = $estWorkingTime;
    }
    if ($estConsumed > 0) {
        $estLeft = $task['task_left'] - $estConsumed;
        $setWorkingTime($task['task_id'], date('Y-m-d'), $estConsumed, $estLeft);
        $filteredTaskList[$i]['task_consumed'] = $task['task_consumed'] + $estConsumed;
        $filteredTaskList[$i]['task_left'] = $estLeft;
    }
    $estWorkingTime -= $estConsumed;
    if ($estWorkingTime <= 0) {
        break;
    }
}

var_dump($filteredTaskList);
