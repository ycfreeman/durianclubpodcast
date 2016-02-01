<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/functions/channels.php';

define('CACHE_KEY', 'durianclubpodcast');
define('EXPIRY', 86400);
define('LONG_EXPIRY', 604800);
define('CHANNEL_QUERYSTRING', 'channel');
define('DIVIDER', '___');

$get_with_lowercase_keys = array_combine(
    array_map('strtolower', array_keys($_GET)),
    array_values($_GET)
);
$memcache = new Memcache;
$getChannel = $get_with_lowercase_keys['channel'];
$requestCacheKey = CACHE_KEY . DIVIDER . $get_with_lowercase_keys['channel'];
$responseType = $get_with_lowercase_keys['type'];

if (!isset($responseType) || $responseType === 'json') {
    require __DIR__ . '/functions/json.php';
} else if ($responseType === 'rss') {
    require __DIR__ . '/functions/rss.php';
}