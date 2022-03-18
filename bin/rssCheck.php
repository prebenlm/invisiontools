<?php
use PHPHtmlParser\Dom;
$is_cron = (!getenv('CRON_MODE')) ? false : true;

require_once(__DIR__ . '/../vendor/autoload.php');
require_once __DIR__ . '/../config/InvisionCommunityLogin.php';
require_once __DIR__ . '/../config/SlackClients.php';
$slack = $slackClients['ipscontributors'];
$slack->setEmoji(':ipslogo:');
$slack->setUsername('IPS Marketplace');

$feeds = [
    'marketplace' => [
        'url' => 'https://invisioncommunity.com/files/files.xml',
        'username' => 'Marketplace',
        'logo' => 'ips-darkpink',
        'footer' => '<#FEED_LINK#|IPS Marketplace>',
        'channels' => ['#ipsfeed'],
        'fallback' => "«<#LINK#|#TITLE#>» was released in the Marketplace.",
        'pretext' => ''
    ],
    'devposts' => [
        'url' => 'https://invisioncommunity.com/discover/66.xml/?member=135437&key=46f7039380c265f48833958eb3f3e3b1',
        'username' => 'Employee',
        'logo' => 'ipslogo',
        'footer' => '<#FEED_LINK#|#FEED_TITLE#>',
        'channels' => ['#ipsfeed'],
        'fallback' => 'IPS manager or developer posted in «<#LINK#|#TITLE#>»',
        'pretext' => ''
    ]
];
$previously = new \StdClass;
$previousFile = __DIR__ . '/../storage/feeds.json';
if (file_exists($previousFile))
{
    $previously = json_decode( file_get_contents($previousFile) );
}

foreach( $feeds as $feed_key => $feed )
{
    $previously->$feed_key = $previously->$feed_key ?? new \StdClass();
    $url = $feed['url'];
    $getter = new \GuzzleHttp\Client();

    $response = FALSE;
    try
    {
        $response = $getter->request( 'GET', $url );
    }
    catch (\GuzzleHttp\Exception\RequestException $e)
    {
        echo "Request failed ({$response->getStatusCode()}), exception {$e->getMessage()}";
        continue;
    }

    if ( $response AND $response->getStatusCode() == 200 AND $response->getBody() )
    {
        $previous = $previously->$feed_key;
        $xml = simplexml_load_string($response->getBody());
    	$slack->setUsername($feed['username']);
        $slack->setEmoji(":{$feed['logo']}:");
        $prev_time = $previous->timestamp ?? strtotime('1th July 2020');
        $guids = $previous->guids ?? [];
        $guids_new = [];
        
        $next_time = $prev_time;
        $items = [];
        foreach( $xml->channel->item as $item ) 
        {
            $items[ (int) $item->guid ?: (string) $item->link ] = [
                'guid'      => (int) $item->guid,
                'title'     => (string) $item->title,
                'desc'      => (string) $item->description,
                'link'      => (string) $item->link,
                'pubDate'   => (string) $item->pubDate,
                'unixdate'  => strtotime( (string) $item->pubDate )
            ];
        }
        
        usort( $items, function( $a, $b )
        {
            return ( $a['unixdate'] < $b['unixdate'] ) ? -1 : ( $a['unixdate'] == $b['unixdate'] ? 0 : 1 );
        });
        
        $attachments = [];
        $authors = [];
        
        foreach( $items as $item )
        {
            $guids_new[] = $item['guid'];
            if ( $item['unixdate'] <= $prev_time )
            {
                continue;
            }
            
            if ( $feed_key == 'marketplace' and in_array($item['guid'], $guids) )
            {
                continue;
            }
            
            $item['desc_stripped'] = preg_replace(["#\t#", "#[ ]+#", "#[\n]+#"], ['', ' ', "\n"], (strip_tags($item['desc'])));
            
            $attach = [
                'fallback' => str_replace(['#LINK#', '#TITLE#'], [$item['link'], $item['title']], $feed['fallback']),
                'title' => $item['title'],
                'title_link' => $item['link'],
                'text' => mb_strimwidth($item['desc_stripped'], 0, 392, '...'),
                'ts' => $item['unixdate'],
                'footer' => str_replace(['#FEED_LINK#', '#FEED_TITLE#'], [(string) $xml->channel->link, (string) $xml->channel->title], $feed['footer']),
                'footer_icon' => 'https://invisioncommunity.com/favicon.ico'
            ];
            
            $get_breadcrumb = false;
            
            if ( $feed_key == 'marketplace' ) {
                $http = new \GuzzleHttp\Client();
                $page = FALSE;
                try
                {
                    $page = $http->request('GET', $item['link']);
                } 
                catch (\GuzzleHttp\Exception\RequestException $e){}
                
                if ( $page AND $page->getStatusCode() == 200 AND $page->getBody() )
                {
                    \phpQuery::newDocument($page->getBody());
                    
                    /* Retrieve author name, link and image */
                    $authorElement = pq('div.ipsPageHeader > div.ipsPageHeader__meta > div.ipsFlex-flex\:11 > div.ipsPhotoPanel > div > p > a');
                    
                    if ( $author = $authorElement->html() )
                    {
                        $attach['author_name'] = $author;
                        
                        if ( $link = $authorElement->attr('href') )
                        {
                            $attach['author_link'] = $link;
                        }
                        $imageElement = pq('div.ipsPageHeader > div.ipsPageHeader__meta > div.ipsFlex-flex\:11 > div.ipsPhotoPanel > a > img');
                        
                        if ( $image = $imageElement->attr('src') )
                        {
                            $attach['author_icon'] = strpos($image, '//') === 0 ? 'https:' . $image : $image;
                            $avg_color = getAverageColor($attach['author_icon']);
                                
                            if ($avg_color)
                            {
                                $attach['color'] = $avg_color;
                            }
                        }
                        
                    }
                    
                    /* Retrieve breadcrumb information */
                    $breadcrumbs = pq('#ipsLayout_contentWrapper > nav.ipsBreadcrumb.ipsBreadcrumb_top.ipsFaded_withHover > ul:nth-child(2) li');
                    $cats = [];
                    
                    foreach ( $breadcrumbs as $b )
                    {
                        if ( $b->nodeValue )
                        {
                            $cats[] = trim($b->nodeValue);
                        }
                    }
                    
                    if ( count($cats) > 1)
                    {
                        array_pop($cats);
                        $attach['footer'] = "<{$xml->channel->link}|" . implode(' » ', $cats) . '>';
                    }
                    
                    /* Find initial price and renewal cost */
                    $price = pq('.cFilePrice');
                    $price = is_object($price) ? $price->html() : false;
                    $renewal = pq('#ipsLayout_mainArea > div > div.ipsColumns.ipsColumns_collapsePhone.ipsClearfix > div > div.ipsPageHeader.ipsClearfix.ipsSpacer_bottom > p > span.ipsType_light');
                    
                    $renewal = is_object($renewal) ? $renewal->html() : false;
                    
                    if ($price !== false)
                    {
                        $attach['fields'][] = [
                            'title' => 'Price',
                            'value' => $price ? $price : 'Free',
                            'short' => true
                        ];
                    }
                    
                    if ($renewal)
                    {
                        $attach['fields'][] = [
                            'title' => 'Renewal Term',
                            'value' => str_replace('Renewal Term:', '', $renewal),
                            'short' => true
                        ];
                    }
                
                    # Image
                    # #ipsLayout_mainArea > div > div.ipsBox.ipsSpacer_top.ipsSpacer_double > section > div > div > ul > li:nth-child(1) > span > img
                }
            }
            else if ( $feed_key == 'devposts' )
            {
                $http = new GuzzleHttp\Client(['headers' => $invisionLogins['community']]);
                $page = FALSE;
                
                try
                {
                    $page = $http->request('GET', $item['link']);
                }
                catch (\GuzzleHttp\Exception\RequestException $e){}

                if ( $page AND $page->getStatusCode() == 200 AND $page->getBody() )
                {
                    \phpQuery::newDocument($page->getBody());
                    preg_match('#comment=(\d+)#', $item['link'], $matches);
                    
                    $commentId = $matches[1] ? $matches[1] : 0;
                    
                    if ($commentId)
                    {
                        /* Retrieve author name, link and image */
                        $authorElement = pq("#elComment_{$commentId} > aside > h3 > strong > a");
                        
                        if ( !$authorElement->html() )
                        {
                            /* It's different for news items */
                            $authorElement = pq("#comment-{$commentId}_wrap > div.ipsComment_header > div.ipsPhotoPanel > div > h3 > strong > a");
                        }
                        
                        if ( $author = $authorElement->html() )
                        {
                            $attach['author_name'] = $author;
                            
                            if ( $link = $authorElement->attr('href') )
                            {
                                $attach['author_link'] = $link;
                            }
                            
                            $imageElement = pq("#elComment_{$commentId} > aside > ul > li.cAuthorPane_photo > div.cAuthorPane_photoWrap > a > img");
                            
                            if ( !$imageElement->attr('src') )
                            {
                                /* It's different for news items */ 
                                $imageElement = pq("#comment-{$commentId}_wrap > div.ipsComment_header > div.ipsPhotoPanel > a > img");
                            }
                            
                            if ( $image = $imageElement->attr('src') )
                            {
                                $attach['author_icon'] = strpos($image, '//') === 0 ? 'https:' . $image : $image;
                                $avg_color = getAverageColor($attach['author_icon']);
                                
                                if ($avg_color)
                                {
                                    $attach['color'] = $avg_color;
                                }
                            }
                        }
                    }
                    
                    /* Retrieve breadcrumb information */
                    $breadcrumbs = pq('#ipsLayout_contentWrapper > nav.ipsBreadcrumb.ipsBreadcrumb_top.ipsFaded_withHover > ul:nth-child(2) li');
                    
                    if ( !$breadcrumbs->html() )
                    {
                        $breadcrumbs = pq('#elCmsPageWrap > div.sNews__nav > ul > li');
                    }
                    
                    $cats = [];
                    
                    foreach ( $breadcrumbs as $b )
                    {
                        $ignores = ['Home', 'Forums'];
                        if ( $b->nodeValue )
                        {
                            $value = trim($b->nodeValue);
                            if ( in_array( $value, $ignores ) )
                            {
                                continue;
                            }
                            $cats[$value] = $value;
                        }
                    }
                    
                    if ( count($cats) > 1)
                    {
                        $lastValue = array_values(array_slice($cats, -1))[0];
                        if  ( $lastValue == $attach['title'] ) {
                            array_pop($cats);
                        }
                        $attach['footer'] = "<{$xml->channel->link}|" . implode(' » ', $cats) . '>';
                    }
                }
            }
            
            $attachments[] = $attach;
            if ( isset( $attach['author_name'] ) )
            {
                $authors[$attach['author_name']] = $attach['author_name'];
            }
            $next_time = ($item['unixdate'] > $next_time) ? $item['unixdate'] : $next_time;
    	}
    	
        if ( count($attachments) )
        {
            $next = $previously;
            $next->$feed_key->timestamp = $next_time;
            $next->$feed_key->guids = $guids_new;
            file_put_contents($previousFile, json_encode( $next, JSON_PRETTY_PRINT ));

            if ( count($authors) == 1 and isset($attachments[0]['author_name']) )
            {
                $slack->setUsername($attachments[0]['author_name']);
                $slack->setEmoji(":{$feed['logo']}:");
            }
            if ( $is_cron ) {
                foreach( $feed['channels'] as $channel ) {
                    $slack->post( $channel, $feed['pretext'], $attachments );
                }
            } else {
                $slack->post( '@tsp', $feed['pretext'], $attachments );
            }
        }
    }
    else
    {
    	#$slack->post('@tsp', "Unsuccessful in retrieving latest update from IPS ({$http_response_header[0]}).");
    }
}

function getAverageColor($sourceURL)
{
	if (strpos($sourceURL, 'https') === 0)
	{
	    $image = imagecreatefromstring(file_get_contents($sourceURL));
	    if ( $image )
	    {
	        $scaled = imagescale($image, 1, 1, IMG_BICUBIC); 
	        $index = imagecolorat($scaled, 0, 0);
	        $rgb = imagecolorsforindex($scaled, $index); 
	        $red = round(round(($rgb['red'] / 0x33)) * 0x33); 
	        $green = round(round(($rgb['green'] / 0x33)) * 0x33); 
	        $blue = round(round(($rgb['blue'] / 0x33)) * 0x33);
	        return sprintf('#%02X%02X%02X', $red, $green, $blue);
	    }
	}
    return false;
 }
