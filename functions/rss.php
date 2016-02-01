<?php

global $getChannel;

date_default_timezone_set('UTC');

use \FeedWriter\RSS2;

$responseCacheKey = $requestCacheKey . DIVIDER . 'rss';
$responseFeed = $memcache->get($responseCacheKey);

if ($responseFeed === false){
    $feed = new RSS2;

    $feed->setTitle($getChannel ?: "All Channels");
    $feed->setLink("$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
    $feed->setDescription($getChannel ?: "All Channels");

    // construct feed here
    $responses = getResponses($getChannel);

    if ($responses) {
        foreach ($responses as $podcast) {
            $newItem = $feed->createNewItem();
            $newItem->setTitle($podcast['title']);
            $newItem->setLink($podcast['m4a']);
            $newItem->setDescription(($podcast['title']));
            $newItem->addEnclosure($podcast['m4a'], $podcast['length'], 'audio/mpeg');
            $feed->addItem($newItem);
        }
    }

    $responseFeed = $feed->generateFeed();
    $memcache->set($responseCacheKey, $responseFeed, MEMCACHE_COMPRESSED, EXPIRY);
}

if ($responseFeed) {
    header('Content-Type: text/xml');
    exit($responseFeed);
}

// # Otherwise, bad request
header('status: 400 Bad Request', true, 400);