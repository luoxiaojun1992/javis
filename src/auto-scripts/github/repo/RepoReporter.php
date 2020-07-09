<?php

require __DIR__ . '/../../../../vendor/autoload.php';

$config = require __DIR__ . '/../config/github.php';

$client = new \Github\Client();

$client->authenticate(
    $config['token'],
    null,
    Github\Client::AUTH_ACCESS_TOKEN
);

$userName = $config['username'];
$repos = $config['repos'];

$repoReport = (new Console_Table());
$repoReportData = [];

foreach ($repos as $repo) {
    $repoName = $repo['name'];

    $repoInfo = $client->api('repo')->show($userName, $repoName);

    $repoReportData[] = [
        $repoName, $repoInfo['stargazers_count'], implode(',', (array)$repoInfo['language']),
        $repoInfo['forks'],
    ];
}

echo $repoReport->fromArray(
    ['Repo Name', 'Repo Stars', 'Repo Language', 'Repo Forks'], $repoReportData
), PHP_EOL;
