<?php

define("CACHE_KEY", "durianclubpodcast");
define("EXPIRY", 86400);
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

$memcache = new Memcache;
//$memcache->delete(CACHE_KEY);

$responseJson = $memcache->get(CACHE_KEY);

if ($responseJson === false) {

    ini_set('default_socket_timeout', 2);

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
            $hash["startTime"]
        ];
    }

    function channelResponse(array $hash)
    {
        $urlTemplate = "http://media.emit.com/%s/chinese/%s%s%s%s/aac_mid.m4a";

        if (array_key_exists("day", $hash)) {
            $date = new DateTime('@' . strtotime("previous " . $hash["day"]));
        } else {
            $date = new DateTime();
        }
        $podcasts = [];

        for ($i = 0; $i < 5; $i++) {
            $currDate = $date->modify("-$i week");
            $url = vsprintf($urlTemplate, transformChannel($currDate, $hash));
            $headers = get_headers($url, 1);
            if (strpos(implode("", $headers), "200 OK") !== false) {
                $podcasts[] = [
                    "date" => $currDate->format("D d M Y"),
                    "m4a" => $url
                ];
            } else {
                continue;
            }
        }

        return [
            "name" => $hash["name"],
            "otherName" => $hash["otherName"],
            "podcasts" => $podcasts
        ];
    }

    $responses = [];
    foreach ($CHANNELS as $key => $value) {
        $responses[$key] = channelResponse($value);
    }
    $responseJson = json_encode($responses);

    $memcache->set(CACHE_KEY, $responseJson);

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


# JSON if no callback
if (!isset($_GET['callback']))
    exit($responseJson);

# JSONP if valid callback
if (is_valid_callback($_GET['callback']))
    exit("{$_GET['callback']}($responseJson)");

// # Otherwise, bad request
header('status: 400 Bad Request', true, 400);

