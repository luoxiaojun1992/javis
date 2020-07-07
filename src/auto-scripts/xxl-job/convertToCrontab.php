<?php

require __DIR__ . '/../../../vendor/autoload.php';

function fetchXXLJobTasks($callback)
{
    $config = require __DIR__ . '/config/db.php';

    $pdo = new \PDO(
        $config['dsn'],
        $config['username'],
        $config['passwd'],
        $config['options']
    );

    $sql = 'SELECT * FROM `xxl_job_info` WHERE `glue_type` = "GLUE_SHELL" LIMIT %s,%s';

    $offset = 0;
    $limit = 100;

    do {
        $stmt = $pdo->prepare(sprintf($sql, $offset, $limit));

        if ($stmt->execute()) {
            $xxlJobTasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            call_user_func($callback, $xxlJobTasks);
            $xxlJobTaskCnt = count($xxlJobTasks);
            $offset += $limit;
        } else {
            $xxlJobTaskCnt = 0;
        }
    } while ($xxlJobTaskCnt >= $limit);
}

function filterGlueMark($glueMark)
{
    if (preg_match("/[\x{4e00}-\x{9fa5}]+/u", $glueMark)) {
        return (new \Overtrue\Pinyin\Pinyin())->abbr($glueMark);
    }

    return $glueMark;
}

/**
 * @param $xxlJobTask
 * @param $shellPath
 * @return string
 * @throws Exception
 */
function convertXXLToCrontab($xxlJobTask, $shellPath)
{
    $jobCron = $xxlJobTask['job_cron'];
    $jobCronArr = explode(' ', $jobCron);
    $crontabArr = array_slice($jobCronArr, 1, 5);

    $filtered = array_walk($crontabArr, function (&$val) {
        if ($val === '?') {
            $val = '*';
        }
    });
    if (!$filtered) {
        throw new \Exception('Invalid job cron');
    }

    return implode(' ', $crontabArr) . ' ' . realpath($shellPath) . ' ' . $xxlJobTask['executor_param'];
}

/**
 * @param $xxlJobTask
 * @return string
 * @throws Exception
 */
function generateShell($xxlJobTask)
{
    $shell = $xxlJobTask['glue_source'];
    $glueRemark = $xxlJobTask['glue_remark'];
    $shellName = filterGlueMark($glueRemark) . '.sh';
    $shellPath = __DIR__ . '/output/shell/' . $shellName;
    if (file_exists($shellPath)) {
        //Avoiding risky override or duplicated pinyin of glue_remark
        throw new \Exception('Shell existed');
    }

    if (!file_put_contents($shellPath, $shell)) {
        throw new \Exception('Failed to generate shell');
    }

    return $shellPath;
}

if (file_exists(__DIR__ . '/output/crontab.txt')) {
    throw new \Exception('Crontab file existed');
}

fetchXXLJobTasks(function ($xxlJobTasks) {
    $outputCronExprArr = [];

    foreach ($xxlJobTasks as $xxlJobTask) {
        $shellPath = generateShell($xxlJobTask);
        $cronExpr = convertXXLToCrontab($xxlJobTask, $shellPath);

        $xxlJobTaskStatus = $xxlJobTask['trigger_status'];
        if ($xxlJobTaskStatus === 0) {
            $outputCronExpr = '# ' . $cronExpr;
        } else {
            $outputCronExpr = $cronExpr;
        }

        $outputCronExprArr[] = $outputCronExpr;
    }

    if (count($outputCronExprArr) > 0) {
        file_put_contents(
            __DIR__ . '/output/crontab.txt',
            implode(PHP_EOL, $outputCronExprArr) . PHP_EOL,
            FILE_APPEND|LOCK_EX
        );
    }
});
