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

$globalIssueReport = (new Console_Table());
$globalIssueReportData = [];

foreach ($repos as $repo) {
    $repoName = $repo['name'];

    echo 'Repo "' . $repoName . '" Issue Report:', PHP_EOL;

    $issueList = $client->api('issue')->all($userName, $repoName, array('state' => 'open'));

    $globalIssueReportData[] = [$repoName, count($issueList)];

    $repoIssueReport = (new Console_Table());
    $repoIssueReportData = [];

    foreach ($issueList as $issue) {
        $labelNames = [];
        foreach ($issue['labels'] as $label) {
            $labelNames[] = $label['name'];
        }

        $repoIssueReportData[] = [
            $issue['title'], $issue['number'], implode(', ', $labelNames), $issue['comments'],
            $issue['created_at'], $issue['updated_at'],
        ];
    }

    echo $repoIssueReport->fromArray(
        ['Title', 'Number', 'Labels', 'Comments', 'Created At', 'Updated At'],
        $repoIssueReportData
    ), PHP_EOL;
}

echo 'Global Issue Report:', PHP_EOL;
echo $globalIssueReport->fromArray(['Repo Name', 'Issue Count'], $globalIssueReportData), PHP_EOL;
