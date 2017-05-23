<?php

/**
 * usage:
 * http://durianclubpodcast.appspot.com/{type}/{underscore separated channels}
 * e.g.
 * http://durianclubpodcast.appsport.com/rss/durianclub_digitalchat
 */


require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/functions/channels.php';

define('CACHE_KEY', 'durianclubpodcast');
define('EXPIRY', 86400);
define('LONG_EXPIRY', 604800);
define('CHANNEL_QUERYSTRING', 'channel');
define('DIVIDER', '___');
ini_set('default_socket_timeout', 5);

$memcache = new Memcache;
$getChannel = $q[2] ?: '';
$requestCacheKey = $q[2] ?: '';
$responseType = $q[1] ?: 'json';

switch ($responseType) {
    case 'json':
        require __DIR__ . '/functions/json.php';
        return;
    case 'rss':
        require __DIR__ . '/functions/rss.php';
        return;
    case 'atom':
        require __DIR__ . '/functions/atom.php';
        return;
}