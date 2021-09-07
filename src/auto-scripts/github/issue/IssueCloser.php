<?php

require __DIR__ . '/../../../../vendor/autoload.php';

function issueWithQuestion($issue)
{
    foreach ($issue['labels'] as $label) {
        if (in_array($label['name'], ['question', 'invalid', 'wontfix'])) {
            return true;
        }
    }

    return false;
}

function filterIssues($issues)
{
    foreach ($issues as $i => $issue) {
        if (!issueWithQuestion($issue)) {
            unset($issues[$i]);
        }
        if (!issueExpired($issue)) {
            unset($issues[$i]);
        }
    }

    return $issues;
}

function issueExpired($issue, $ttl = 3)
{
    $updatedAt = \Carbon\Carbon::createFromTimeString($issue['updated_at']);
    return \Carbon\Carbon::now()->diffInDays($updatedAt) > $ttl;
}

function closeIssue($client, $issue, $user, $repo)
{
    $client->api('issue')->update($user, $repo, $issue['number'], array('state' => 'closed'));
}

function addIssueComment($client, $issue, $user, $repo, $comment)
{
    $client->api('issue')->comments()->create(
        $user, $repo, $issue['number'],
        [
            'body' => $comment,
        ]
    );
}

$config = require __DIR__ . '/../config/github.php';

$client = new \Github\Client();

$client->authenticate(
    $config['token'],
    null,
    Github\Client::AUTH_ACCESS_TOKEN
);

$userName = $config['username'];
$repos = $config['repos'];

foreach ($repos as $repo) {
    $repoName = $repo['name'];

    echo 'Processing repo "' . $repoName . '"', PHP_EOL;

    $issues = $client->api('issue')->all($userName, $repoName, array('state' => 'open'));

    foreach (filterIssues($issues) as $issue) {
        addIssueComment(
            $client,
            $issue,
            $userName,
            $repoName,
            '我将结束这个问题。如果您有任何问题，请随时重新讨论这个问题。'
        );
        addIssueComment(
            $client,
            $issue,
            $userName,
            $repoName,
            'I will close this issue. If you have any question else, please feel free to reopen this issue.'
        );
        closeIssue($client, $issue, $userName, $repoName);
        echo 'Closed issue ' . ((string)($issue['number'])) . ' of repo "' . $repoName . '"', PHP_EOL;
    }
}
