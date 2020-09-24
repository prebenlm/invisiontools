<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../config/SlackClients.php');

$storagePath = __DIR__ . '/../storage/ipsversions.json';

if ( file_exists( $storagePath ) )
{
    $currentVersions = json_decode( file_get_contents( $storagePath ) );
}
else
{
    $currentVersions = (object) [
        'dev' => (object) ['longversion' => 0],
        'supported' => (object) ['longversion' => 0]
    ];
}

$liveChecker = new \Invision\VersionChecker( $currentVersions->supported->longversion ?: null );
$devChecker = new \Invision\VersionChecker( $currentVersions->dev->longversion ?: null );
$devChecker->isDevelopment( true );

$live = $liveChecker->get();
$dev = $devChecker->get();

$versions = [
	'supported' => $live,
	'dev' => $dev
];

if( !$live )
{
	/* Retry */
	echo "Failed first attempt...\n";
	$slackClients['ipscontributors']->post('@tsp', "Unsuccessful in retrieving latest update from IPS (" . $liveChecker->getResponse()->getResponseCode() . ").");
	// @todo retry after 120 seconds?
	exit;
}

$posted = false;
$newVersions = is_object( $currentVersions ) ? $currentVersions : new \StdClass;
foreach( $versions as $k => $v )
{
	$current = $currentVersions->$k;
	if( $v->released )
	{
        if($current->longversion == $v->longversion)
        {
            continue;
        }

        $attachments = [];
        
        if($v->changes)
        {
            foreach($v->changes as $_k => $change)
            {
                if ($change == $v->version)
                {
                    continue;
                }
                $attach = [
                    'fallback' => "[$_k]: {$change}",
                    'text' => "*{$_k}:* {$change}",
                    'mrkdwn_in' => ["text"]
                ];
                $attachments[] = $attach;
            }
        }


        if( $posted != $v->longversion )
        {
            $posted = $v->longversion;
            $message  = "Version {$v->version}" . ( ( $k != 'dev' ) ? '' : " (Beta)" ) . " was just released";
            $message .= ( $v->security ) ? ' and is a security release!' : '.';
            $message .= " View <https://invisioncommunity.com/release-notes/|Release Notes>";
            $message .= " _({$v->longversion})_";
            
            $slackClients['ipscontributors']->post( '@tsp', $message, $attachments );
            //$slackClients['ipscontributors']->post( '#updates', $message, $attachments );
        }

        $newVersions->$k = $v;
	}
}

file_put_contents($storagePath, json_encode( $newVersions, JSON_PRETTY_PRINT ) );