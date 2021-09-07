<?php

if (date_default_timezone_get() !== 'PRC') {
    date_default_timezone_set('PRC');
}

$fd = opendir(__DIR__ . '/output');

$aggReportFd = fopen(__DIR__ . '/output/zipkin-agg-report-' . date('Y-m-d') . '.csv', 'a');

fputcsv($aggReportFd, ['report_date', 'success_max_duration', 'server_error_times', 'client_error_times']);

$aggData = [];

while ($fileOrDir = readdir($fd)) {
    if (stripos($fileOrDir, 'zipkin-report') === 0) {
        $fileName = substr($fileOrDir, 0, -1 * strlen('.csv'));
        $fileNameParts = explode('-', $fileName);
        $reportDate = implode('-', array_slice($fileNameParts, 5, 3));

        $originDataFd = fopen(__DIR__ . '/output/' .$fileOrDir, 'r');
        fgetcsv($originDataFd);
        $originData = fgetcsv($originDataFd);
        fclose($originDataFd);

        $successMaxDuration = $originData[0];
        if (strpos($successMaxDuration, '秒')) {
            $successMaxDuration = mb_substr($successMaxDuration, 0, -1);
        } else {
            $successMaxDuration = $successMaxDuration / 1000000;
        }

        $serverErrorTimes = $originData[1];
        if (strpos($serverErrorTimes, '次')) {
            $serverErrorTimes = mb_substr($serverErrorTimes, 0, -1);
        }

        $clientErrorTimes = $originData[2];
        if (strpos($clientErrorTimes, '次')) {
            $clientErrorTimes = mb_substr($clientErrorTimes, 0, -1);
        }

        $aggData[] = [$reportDate, $successMaxDuration, $serverErrorTimes, $clientErrorTimes];
    }
}

array_multisort($aggData, array_column($aggData, 1));

array_walk($aggData, function ($row) use ($aggReportFd) {
    fputcsv($aggReportFd, $row);
});

fclose($aggReportFd);

closedir($fd);
