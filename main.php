<?php

//require 'api_cache/API_cache.php';
//
//$cache_file = 'durianclub.json';
//$api_call = 'http://4eb.org.au/views/ajax?tid=12&view_name=episodes&view_display_id=page_1&view_args=%3FGlobal%3DAll&view_path=ondemand&view_base_path=ondemand&view_dom_id=1&pager_element=0';
//$cache_for = 5; // cache results for five minutes
//
//$api_cache = new API_cache ($api_call, $cache_for, $cache_file);
//if (!$res = $api_cache->get_api_cache())
//    $res = '{"error": "Could not load cache"}';
//
//ob_start();
//echo $res;
$json = file_get_contents('http://4eb.org.au/views/ajax?tid=12&view_name=episodes&view_display_id=page_1&view_args=%3FGlobal%3DAll&view_path=ondemand&view_base_path=ondemand&view_dom_id=1&pager_element=0');

/**
 * search for "Chinese show aired Saturday night"
 */

$split = explode("Chinese show aired Saturday night", $json);

/**
 * search for "\\x3c"
 */
$split2 = explode("\\x3c", $split[1]);


/**
 * look for match http:// * .m4a lines
 */
$pattern = '/http:\/\/.*.m4a/';
preg_match($pattern, $split[1], $matches);


$response = array('date' => trim($split2[0]), 'm4a' => trim($matches[0]));

$responseJson = json_encode($response);

/**
 * { date: $split2[0], m4a: $matches[0]}
 */

header('Cache-Control: no-cache, must-revalidate');
//header('Expires: ' . $api_cache->get_expires_datetime());
//header ('Content-length: ' . strlen($responseJson));
header("access-control-allow-origin: *");
header('Content-Type: application/javascript');


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

