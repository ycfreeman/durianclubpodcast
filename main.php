<?php

define("CACHE_KEY", "durianclubpodcast");
define("EXPIRY", 86400);
define("LONG_EXPIRY", 604800);
define("CHANNEL_QUERYSTRING", "channel");
define("DIVIDER", "___");
$CHANNELS = [
    "durianclub" => [
        "name" => "Durian Club",
        "otherName" => "榴槤俱樂部",
        "channel" => "4eb",
        "day" => "saturday",
        "startTime" => "2230"
    ],
    "digitalchat" => [
        "name" => "Digital Chat",
        "otherName" => "數碼講",
        "channel" => "4eb-d",
        "day" => "saturday",
        "startTime" => "1930"
    ]
];
$get_with_lowercase_keys = array_combine(
    array_map("strtolower", array_keys($_GET)),
    array_values($_GET)
);
$memcache = new Memcache;
$getChannel = $get_with_lowercase_keys["channel"];
$requestCacheKey = CACHE_KEY . DIVIDER . $get_with_lowercase_keys["channel"];
$responseJson = $memcache->get($requestCacheKey);


if ($responseJson === false) {

    ini_set('default_socket_timeout', 5);


    function transformChannel(DateTime $date, array $hash)
    {
        $year = $date->format("Y");
        $month = $date->format("m");
        $day = $date->format("d");
        return [
            $hash["channel"],
            $year,
            $month,
            $day,
            $hash["startTime"],
            $year .
            $month .
            $day .
            $hash["startTime"],
        ];
    }

    function constructPodcast(DateTime $dateTime, $url)
    {
        return [
            "date" => $dateTime->format("D d M Y"),
            "m4a" => $url
        ];
    }

    function channelResponse(array $hash)
    {
        global $memcache;
//        $urlTemplate = "http://media.emit.com/%s/chinese/%s%s%s%s/aac_mid.m4a";
        $urlTemplate = "http://emit-media-production.s3.amazonaws.com/%s/chinese/%s/%s/%s/%s/%s_chinese_64.m4a";
        if (array_key_exists("day", $hash)) {
            $date = new DateTime('@' . strtotime("previous " . $hash["day"]));
        } else {
            $date = new DateTime();
        }
        $podcasts = [];


        for ($i = 0; $i < 15; $i++) {
            $currDate = $date->modify("-1 week");
            $url = vsprintf($urlTemplate, transformChannel($currDate, $hash));
            $cacheKey = base64_encode($url);
            $cachedUrl = $memcache->get($cacheKey);

            if ($cachedUrl === false) {
                $context = [
                    "http" => [
                        "method" => "HEAD",
                        "follow_location" => 0
                    ]
                ];
                $context = stream_context_create($context);
                $response = file_get_contents($url, false, $context);
                $httpCode = 404;
                if (!empty($http_response_header)) {
                    sscanf($http_response_header[0], 'HTTP/%*d.%*d %d', $httpCode);
                }
                if ($httpCode == 200) {
                    $memcache->set($cacheKey, $url, MEMCACHE_COMPRESSED, LONG_EXPIRY);
                } else {
                    continue;
                }
            }
            $podcasts[] = constructPodcast($currDate, $url);
        }

        return [
            "name" => $hash["name"],
            "otherName" => $hash["otherName"],
            "podcasts" => $podcasts
        ];
    }

    $responses = [];

    if (!isset($getChannel)) {
        foreach ($CHANNELS as $key => $value) {
            $responses[$key] = channelResponse($value);
        }


    } else {
        if (array_key_exists($getChannel, $CHANNELS)) {
            $responses = channelResponse($CHANNELS[$getChannel]);
        } else if (strpos($getChannel, "|")) {
            $requestChannels = explode("|", $getChannel);

            foreach ($requestChannels as $channel) {
                $responses[$channel] = channelResponse($CHANNELS[$channel]);
            }
        }
    }

    if ($responses) {
        $responseJson = json_encode($responses);
        $memcache->set($requestCacheKey, $responseJson, MEMCACHE_COMPRESSED, EXPIRY);
    }

}


/**
 * { date: $split2[0], m4a: $matches[0]}
 */

header('Cache-Control: no-cache, must-revalidate');
//header('Expires: ' . $api_cache->get_expires_datetime());
//header ('Content-length: ' . strlen($responseJson));
header("access-control-allow-origin: *");
header('Content-Type: application/json');


function is_valid_callback($subject)
{
    $identifier_syntax
        = '/^[$_\p{L}][$_\p{L}\p{Mn}\p{Mc}\p{Nd}\p{Pc}\x{200C}\x{200D}]*+$/u';

    $reserved_words = array('break', 'do', 'instanceof', 'typeof', 'case',
        'else', 'new', 'var', 'catch', 'finally', 'return', 'void', 'continue',
        'for', 'switch', 'while', 'debugger', 'function', 'this', 'with',
        'default', 'if', 'throw', 'delete', 'in', 'try', 'class', 'enum',
        'extends', 'super', 'const', 'export', 'import', 'implements', 'let',
        'private', 'public', 'yield', 'interface', 'package', 'protected',
        'static', 'null', 'true', 'false');

    return preg_match($identifier_syntax, $subject)
    && !in_array(mb_strtolower($subject, 'UTF-8'), $reserved_words);
}

if ($responseJson) {
    # JSON if no callback
    if (!isset($_GET['callback']))
        exit($responseJson);

    # JSONP if valid callback
    if (is_valid_callback($_GET['callback']))
        exit("{$_GET['callback']}($responseJson)");
}


// # Otherwise, bad request
header('status: 400 Bad Request', true, 400);

