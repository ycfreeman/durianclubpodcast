<?php

global $getChannel;
global $requestCacheKey;


date_default_timezone_set('UTC');

use \FeedWriter\ATOM;

$responseCacheKey = $requestCacheKey . DIVIDER . 'atom';
$responseFeed = $memcache->get($responseCacheKey);

if ($responseFeed === false){
    $feed = new ATOM;

    $channel = getChannel($getChannel);
    $channelTitle = 'Radio 4eb Chinese - '. $channel['name'].' - '.$channel['otherName'];

    $feed->setDate(date('Y-m-d H:i:s'));
    $feed->setSelfLink('https://durianclubpodcast.appspot.com/atom');
    $feed->setTitle($getChannel ? $channelTitle : "Radio 4eb Chinese - All Channels");
    $feed->setLink("$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");
    $feed->setDescription($getChannel ? $channelTitle : "Radio 4eb Chinese - All Channels");

    // construct feed here
    $responses = getResponses($getChannel);

    if ($responses) {
        foreach ($responses as $podcast) {
            $newItem = $feed->createNewItem();
            $newItem->setAuthor('Radio 4eb Chinese');
            $newItem->setTitle($podcast['title']);
            $newItem->setLink($podcast['m4a']);
            $newItem->setDate($podcast['pubDate']);
            $newItem->setDescription(($podcast['title']));
            $newItem->addEnclosure($podcast['m4a'], $podcast['length'], 'audio/mpeg');
            $feed->addItem($newItem);
        }
    }

    $responseFeed = $feed->generateFeed();
//    $memcache->set($responseCacheKey, $responseFeed, MEMCACHE_COMPRESSED, EXPIRY);
}

if ($responseFeed) {
    header("Access-Control-Allow-Origin: *");
    header('Content-Type: text/xml');
    exit($responseFeed);
}

// # Otherwise, bad request
header('status: 400 Bad Request', true, 400);