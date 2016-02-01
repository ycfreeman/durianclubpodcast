<?php

global $getChannel;

$responseCacheKey = $requestCacheKey . DIVIDER . 'json';
$responseJson = $memcache->get($responseCacheKey);

if ($responseJson === false) {
    ini_set('default_socket_timeout', 5);

    $responses = getResponses($getChannel);

    if ($responses) {
        $responseJson = json_encode($responses);
        $memcache->set($responseCacheKey, $responseJson, MEMCACHE_COMPRESSED, EXPIRY);
    }

}


/**
 * { date: $split2[0], m4a: $matches[0]}
 */

header('Cache-Control: no-cache, must-revalidate');
//header('Expires: ' . $api_cache->get_expires_datetime());
//header ('Content-length: ' . strlen($responseJson));
header('access-control-allow-origin: *');
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
        exit('{$_GET["callback"]}($responseJson)');
}


// # Otherwise, bad request
header('status: 400 Bad Request', true, 400);

