<?php

global $getChannel;

date_default_timezone_set('UTC');

use \FeedWriter\RSS2;

$responseCacheKey = $requestCacheKey . DIVIDER . 'rss';
//$responseFeed = $memcache->get($responseCacheKey);
$responseFeed = false;

// no cached feed
if ($responseFeed === false){
    $feed = new RSS2;

    $feed->setTitle($getChannel);
    $feed->addGenerator();

    // construct feed here

    $responseFeed = $feed->generateFeed();
//    $memcache->set($responseCacheKey, $responseFeed, MEMCACHE_COMPRESSED, EXPIRY);
}

if ($responseFeed) {
    exit($responseFeed);
}

// # Otherwise, bad request
header('status: 400 Bad Request', true, 400);