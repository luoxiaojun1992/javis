<?php

require_once __DIR__ . '/../../../vendor/autoload.php';

$zipkinConfig = require_once __DIR__ . '/config/zipkin.php';

if (date_default_timezone_get() !== 'PRC') {
    date_default_timezone_set('PRC');
}

$startDate = $argv[1] ?? date('Y-m-d', strtotime('-1 week'));
$endDate = $argv[2] ?? date('Y-m-d');
$startTimestamp = strtotime($startDate . ' 00:00:00');
$endTimestamp = strtotime($endDate . ' 23:59:59');

function formatTime($time)
{
    if ($time >= 1000000) {
        return (string)($time / 1000000) . '秒';
    } elseif ($time > 1000) {
        return (string)($time / 1000) . '毫秒';
    } else {
        return (string)$time . '微秒';
    }
}

$esClient = \Elasticsearch\ClientBuilder::fromConfig($zipkinConfig['es']);

$successMaxDuration = function () use ($esClient, $startTimestamp, $endTimestamp) {
    $result = $esClient->search(array(
        'index' => 'zipkin:span-*',
        'size' => 0,
        'version' => true,
        'body' => array(
            'aggs' => array(
                1 => array(
                    'max' => array(
                        'field' => 'duration',
                    ),
                ),
            ),
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array(
                            'range' => array(
                                'timestamp_millis' => array(
                                    'gte' => $startTimestamp * 1000,
                                    'lte' => $endTimestamp * 1000,
                                    'format' => 'epoch_millis',
                                ),
                            ),
                        ),
                        array(
                            'bool' => array(
                                'must_not' => array(
                                    array(
                                        'terms' =>
                                            array(
                                                '_q' =>
                                                    array(
                                                        'error',
                                                    ),
                                            ),
                                    ),
                                ),
                                'must' => array(
                                    array(
                                        'terms' => array(
                                            'kind' => array(
                                                'SERVER',
                                                'CLIENT',
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            )
        ),
    ));

    return formatTime($result['aggregations'][1]['value'] ?? 0);
};

$serverErrorTimes = function () use ($esClient, $startTimestamp, $endTimestamp) {
    $result = $esClient->search(array(
        'index' => 'zipkin:span-*',
        'size' => 0,
        'version' => true,
        'body' => array(
            'aggs' => array(
                1 => array(
                    'cardinality' => array(
                        'field' => 'id',
                    ),
                ),
            ),
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array(
                            'range' => array(
                                'timestamp_millis' => array(
                                    'gte' => $startTimestamp * 1000,
                                    'lte' => $endTimestamp * 1000,
                                    'format' => 'epoch_millis',
                                ),
                            ),
                        ),
                        array(
                            'bool' => array(
                                'must' => array(
                                    array(
                                        'terms' =>
                                            array(
                                                '_q' =>
                                                    array(
                                                        "http.status_code=500",
                                                        "http.status_code=501",
                                                        "http.status_code=502",
                                                        "http.status_code=503",
                                                        "http.status_code=504",
                                                    ),
                                            ),
                                    ),
                                    array(
                                        'terms' => array(
                                            'kind' => array(
                                                'SERVER',
                                                'CLIENT',
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            )
        ),
    ));

    return (string)($result['aggregations'][1]['value'] ?? 0) . '次';
};

$clientErrorTimes = function () use ($esClient, $startTimestamp, $endTimestamp) {
    $result = $esClient->search(array(
        'index' => 'zipkin:span-*',
        'size' => 0,
        'version' => true,
        'body' => array(
            'aggs' => array(
                1 => array(
                    'cardinality' => array(
                        'field' => 'id',
                    ),
                ),
            ),
            'query' => array(
                'bool' => array(
                    'must' => array(
                        array(
                            'range' => array(
                                'timestamp_millis' => array(
                                    'gte' => $startTimestamp * 1000,
                                    'lte' => $endTimestamp * 1000,
                                    'format' => 'epoch_millis',
                                ),
                            ),
                        ),
                        array(
                            'bool' => array(
                                'must' => array(
                                    array(
                                        'terms' =>
                                            array(
                                                '_q' =>
                                                    array(
                                                        "http.status_code=400",
                                                        "http.status_code=401",
                                                        "http.status_code=403",
                                                        "http.status_code=404",
                                                        "http.status_code=405",
                                                        "http.status_code=421",
                                                        "http.status_code=429",
                                                        "http.status_code=449",
                                                        "http.status_code=499",
                                                    ),
                                            ),
                                    ),
                                    array(
                                        'terms' => array(
                                            'kind' => array(
                                                'SERVER',
                                                'CLIENT',
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                        ),
                    ),
                ),
            )
        ),
    ));

    return (string)($result['aggregations'][1]['value'] ?? 0) . '次';
};

$successDurationTop10 = function () use ($esClient, $startTimestamp, $endTimestamp) {
    $result = $esClient->search(array(
        'index' => 'zipkin:span-*',
        'size' => 0,
        'version' => true,
        'body' => [
            'aggs' => array (
                2 =>
                    array (
                        'terms' =>
                            array (
                                'field' => 'name',
                                'size' => 10,
                                'order' =>
                                    array (
                                        '2-orderAgg' => 'desc',
                                    ),
                            ),
                        'aggs' =>
                            array (
                                1 =>
                                    array (
                                        'top_hits' =>
                                            array (
                                                'docvalue_fields' =>
                                                    array (
                                                        0 => 'duration',
                                                    ),
                                                '_source' => 'duration',
                                                'size' => 1,
                                                'sort' =>
                                                    array (
                                                        0 =>
                                                            array (
                                                                'duration' =>
                                                                    array (
                                                                        'order' => 'desc',
                                                                    ),
                                                            ),
                                                    ),
                                            ),
                                    ),
                                '2-orderAgg' =>
                                    array (
                                        'max' =>
                                            array (
                                                'field' => 'duration',
                                            ),
                                    ),
                            ),
                    ),
            ),
            'query' => array(
                'bool' =>
                    array(
                        'must' =>
                            array(
                                array(
                                    'range' =>
                                        array(
                                            'timestamp_millis' =>
                                                array(
                                                    'gte' => $startTimestamp * 1000,
                                                    'lte' => $endTimestamp * 1000,
                                                    'format' => 'epoch_millis',
                                                ),
                                        ),
                                ),
                                array(
                                    'bool' =>
                                        array(
                                            'must_not' =>
                                                array(
                                                    array(
                                                        'terms' =>
                                                            array(
                                                                '_q' =>
                                                                    array(
                                                                        'error',
                                                                    ),
                                                            ),
                                                    ),
                                                ),
                                            'must' =>
                                                array(
                                                    array(
                                                        'terms' =>
                                                            array(
                                                                'kind' =>
                                                                    array(
                                                                        'SERVER',
                                                                        'CLIENT',
                                                                    ),
                                                            ),
                                                    ),
                                                ),
                                        ),
                                ),
                            ),
                    ),
            ),]
    ));

    $urls = $result['aggregations'][2]['buckets'] ?? [];
    array_walk($urls, function (&$value) {
        $value = ($value['key'] ?? '') . '   ' . formatTime($value['2-orderAgg']['value'] ?? 0);
    });

    return implode(PHP_EOL, $urls);
};

$serverErrorRequestTop10 = function () use ($esClient, $startTimestamp, $endTimestamp) {
    $result = $esClient->search(array(
        'index' => 'zipkin:span-*',
        'size' => 0,
        'version' => true,
        'body' => [
            'aggs' => array(
                2 =>
                    array(
                        'terms' =>
                            array(
                                'field' => 'name',
                                'size' => 10,
                                'order' =>
                                    array(
                                        1 => 'desc',
                                    ),
                            ),
                        'aggs' =>
                            array(
                                1 =>
                                    array(
                                        'cardinality' =>
                                            array(
                                                'field' => 'id',
                                            ),
                                    ),
                            ),
                    ),
            ),
            'query' => array(
                'bool' =>
                    array(
                        'must' =>
                            array(
                                array(
                                    'range' => array(
                                        'timestamp_millis' => array(
                                            'gte' => $startTimestamp * 1000,
                                            'lte' => $endTimestamp * 1000,
                                            'format' => 'epoch_millis',
                                        ),
                                    ),
                                ),
                                array(
                                    'bool' => array(
                                        'must' => array(
                                            array(
                                                'terms' => array(
                                                    '_q' =>
                                                        array(
                                                            'http.status_code=500',
                                                            'http.status_code=501',
                                                            'http.status_code=502',
                                                            'http.status_code=503',
                                                            'http.status_code=504',
                                                        ),
                                                ),
                                            ),
                                            array(
                                                'terms' => array(
                                                    'kind' => array(
                                                        'SERVER',
                                                        'CLIENT',
                                                    ),
                                                ),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                    ),
            ),
        ]
    ));

    $urls = $result['aggregations'][2]['buckets'] ?? [];
    array_walk($urls, function (&$value) {
        $value = ($value['key'] ?? '') . '   ' . ($value[1]['value'] ?? 0) . '次';
    });

    return implode(PHP_EOL, $urls);
};

$errorRequestTop10 = function () use ($esClient, $startTimestamp, $endTimestamp) {
    $result = $esClient->search(array(
        'index' => 'zipkin:span-*',
        'size' => 0,
        'version' => true,
        'body' => [
            'aggs' => array(
                2 =>
                    array(
                        'terms' =>
                            array(
                                'field' => 'name',
                                'size' => 10,
                                'order' =>
                                    array(
                                        1 => 'desc',
                                    ),
                            ),
                        'aggs' =>
                            array(
                                1 =>
                                    array(
                                        'cardinality' =>
                                            array(
                                                'field' => 'id',
                                            ),
                                    ),
                            ),
                    ),
            ),
            'query' => array(
                'bool' =>
                    array(
                        'must' =>
                            array(
                                array(
                                    'range' => array(
                                        'timestamp_millis' => array(
                                            'gte' => $startTimestamp * 1000,
                                            'lte' => $endTimestamp * 1000,
                                            'format' => 'epoch_millis',
                                        ),
                                    ),
                                ),
                                array(
                                    'bool' => array(
                                        'must' => array(
                                            array(
                                                'terms' => array(
                                                    '_q' =>
                                                        array(
                                                            'error',
                                                        ),
                                                ),
                                            ),
                                            array(
                                                'terms' => array(
                                                    'kind' => array(
                                                        'SERVER',
                                                        'CLIENT',
                                                    ),
                                                ),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                    ),
            ),
        ]
    ));

    $urls = $result['aggregations'][2]['buckets'] ?? [];
    array_walk($urls, function (&$value) {
        $value = ($value['key'] ?? '') . '   ' . ($value[1]['value'] ?? 0) . '次';
    });

    return implode(PHP_EOL, $urls);
};

$successRequestTop10 = function () use ($esClient, $startTimestamp, $endTimestamp) {
    $result = $esClient->search(array(
        'index' => 'zipkin:span-*',
        'size' => 0,
        'version' => true,
        'body' => [
            'aggs' => array(
                2 =>
                    array(
                        'terms' =>
                            array(
                                'field' => 'name',
                                'size' => 10,
                                'order' =>
                                    array(
                                        1 => 'desc',
                                    ),
                            ),
                        'aggs' =>
                            array(
                                1 =>
                                    array(
                                        'cardinality' =>
                                            array(
                                                'field' => 'id',
                                            ),
                                    ),
                            ),
                    ),
            ),
            'query' => array(
                'bool' =>
                    array(
                        'must' =>
                            array(
                                array(
                                    'range' => array(
                                        'timestamp_millis' => array(
                                            'gte' => $startTimestamp * 1000,
                                            'lte' => $endTimestamp * 1000,
                                            'format' => 'epoch_millis',
                                        ),
                                    ),
                                ),
                                array(
                                    'bool' => array(
                                        'must_not' => array(
                                            array(
                                                'terms' => array(
                                                    '_q' =>
                                                        array(
                                                            'error',
                                                        ),
                                                ),
                                            ),
                                        ),
                                        'must' => array(
                                            array(
                                                'terms' => array(
                                                    'kind' => array(
                                                        'SERVER',
                                                        'CLIENT',
                                                    ),
                                                ),
                                            ),
                                        ),
                                    ),
                                ),
                            ),
                    ),
            ),
        ]
    ));

    $urls = $result['aggregations'][2]['buckets'] ?? [];
    array_walk($urls, function (&$value) {
        $value = ($value['key'] ?? '') . '   ' . ($value[1]['value'] ?? 0) . '次';
    });

    return implode(PHP_EOL, $urls);
};

$report = [
    'success_max_duration' => call_user_func($successMaxDuration),
    'server_error_times' => call_user_func($serverErrorTimes),
    'client_error_times' => call_user_func($clientErrorTimes),
    'success_duration_top10' => call_user_func($successDurationTop10),
    'server_error_top10' => call_user_func($serverErrorRequestTop10),
    'error_top10' => call_user_func($errorRequestTop10),
    'success_top10' => call_user_func($successRequestTop10),
    'start_date' => $startDate,
    'end_date' => $endDate,
];

$fd = fopen(__DIR__ . '/output/zipkin-report-' . $startDate . '-' . $endDate . '.csv', 'w');
fputcsv($fd, array_keys($report));
fputcsv($fd, $report);
fclose($fd);
