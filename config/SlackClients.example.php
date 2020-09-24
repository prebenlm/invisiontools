<?php
$_ipsSlack = new \Slack\Client('https://hooks.slack.com/services/###');
$_ipsSlack->setEmoji(':ips:');
$_ipsSlack->setUsername('IPS Updates');

$slackClients = [
	'ipscontributors' => $_ipsSlack,
];