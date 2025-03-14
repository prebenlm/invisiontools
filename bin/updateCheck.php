<?php
require_once(__DIR__ . '/../vendor/autoload.php');
require_once(__DIR__ . '/../config/SlackClients.php');

foreach([4, 5] as $majorVersion)
{
    $storagePath = __DIR__ . '/../storage/ipsversions' . ($majorVersion === 5 ? '5' : '') . '.json';

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
    $liveChecker->setIsV5( $majorVersion === 5 );
    $devChecker->setIsV5( $majorVersion === 5 );
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
            
            if(isset($v->changes) && $v->changes)
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
                $message .= " View <https://invisioncommunity.com/release-notes" . ( $majorVersion === 5 ? '-v5' : '' ) . "/|release notes>";
                $message .= "/Download <https://remoteservices.invisionpower.com/devtools" . ( $majorVersion === 5 ? '5' : '' ) . "/{$v->longversion}|dev tools>";
                $message .= " _({$v->longversion})_";

                $slackClients['ipscontributors']->post( '#updates', $message, $attachments );
            }

            $newVersions->$k = $v;
        }
    }

    file_put_contents($storagePath, json_encode( $newVersions, JSON_PRETTY_PRINT ) );
}