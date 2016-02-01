<?php
$CHANNELS = [
    'durianclub' => [
        'name' => 'Durian Club',
        'otherName' => '榴槤俱樂部',
        'channel' => '4eb',
        'day' => 'saturday',
        'startTime' => '2230'
    ],
    'digitalchat' => [
        'name' => 'Digital Chat',
        'otherName' => '數碼講',
        'channel' => '4eb-d',
        'day' => 'saturday',
        'startTime' => '1930'
    ],
    'caferhapsody' => [
        'name' => 'Café Rhapsody',
        'otherName' => '咖啡狂想曲',
        'channel' => '4eb',
        'day' => 'friday',
        'startTime' => '2245'
    ],
    'saturday8' => [
        'name' => 'Saturday talk',
        'otherName' => '十方漫談',
        'channel' => '4eb',
        'dayString' => 'first saturday of this month',
        'startTime' => '2000'
    ],
    'sundaymorning' => [
        'name' => 'Good Morning Sunday!',
        'otherName' => '早晨星期日!',
        'channel' => '4eb',
        'day' => 'sunday',
        'startTime' => '1015'
    ],
    'sundayafternoon' => [
        'name' => 'Sunday Afternoon',
        'otherName' => '客座新潮流/午後的布村',
        'channel' => '4eb',
        'day' => 'sunday',
        'startTime' => '1515'
    ],
    'lalaland' => [
        'name' => 'Lala-Land',
        'otherName' => '啦啦世界',
        'channel' => '4eb',
        'day' => 'sunday',
        'startTime' => '2200'
    ]
];


function getResponses($getChannel)
{

    function parseHeaders($headers)
    {
        $head = array();
        foreach ($headers as $k => $v) {
            $t = explode(':', $v, 2);
            if (isset($t[1]))
                $head[strtolower(trim($t[0]))] = trim($t[1]);
            else {
                $head[] = $v;
                if (preg_match('#HTTP/[0-9\.]+\s+([0-9]+)#', $v, $out))
                    $head['response_code'] = intval($out[1]);
            }
        }
        return $head;
    }

    function urlHelper(DateTime $date, array $hash)
    {
        $year = $date->format('Y');
        $month = $date->format('m');
        $day = $date->format('d');
        return [
            $hash['channel'],
            $year,
            $month,
            $day,
            $hash['startTime'],
            $year .
            $month .
            $day .
            $hash['startTime'],
        ];
    }

    function constructPodcast(DateTime $dateTime, $url, $length, $name)
    {
        $formattedDateTime = $dateTime->format('D d M Y');

        return [
            'title' => $name . ' - ' . $formattedDateTime,
            'date' => $formattedDateTime,
            'm4a' => $url,
            'length' => $length
        ];
    }

    function channelResponse(array $hash)
    {
        global $memcache;
//        $urlTemplate = 'http://media.emit.com/%s/chinese/%s%s%s%s/aac_mid.m4a';
        $urlTemplate = 'http://emit-media-production.s3.amazonaws.com/%s/chinese/%s/%s/%s/%s/%s_chinese_64.m4a';
        $decrementString = '-1 week';
        if (array_key_exists('day', $hash)) {
            $date = new DateTime('@' . strtotime('this ' . $hash['day']));
            if ($date >= (new DateTime('now'))->modify('-1 day')) {
                $date->modify('-1 week');
            }
        } else if (array_key_exists('dayString', $hash)) {
            $date = new DateTime('@' . strtotime($hash['dayString']));
            if ($date >= (new DateTime('now'))->modify('-1 day')) {
                $date = new DateTime('@' . strtotime(str_replace('this', 'last', $hash['dayString'])));
            }
            $decrementString = str_replace('this', 'last', $hash['dayString']);
        } else {
            $date = new DateTime('now');
        }
        $podcasts = [];

        for ($i = 0; $i < 15; $i++) {
            $url = vsprintf($urlTemplate, urlHelper($date, $hash));
            if ($url === $podcasts[$i - 1]['m4a']) {
                continue;
            }
            $cacheKey = base64_encode($url);
            $podcast = $memcache->get($cacheKey);

            if ($podcast === false) {
                $length = 0;
                $context = [
                    'http' => [
                        'method' => 'HEAD',
                        'follow_location' => 0
                    ]
                ];
                $context = stream_context_create($context);

                // test if url is valid
                $response = file_get_contents($url, false, $context);
                $httpCode = 404;
                if (!empty($http_response_header)) {
                    $responseHeaders = parseHeaders($http_response_header);
                    $httpCode = $responseHeaders['response_code'];
                    $length = $responseHeaders['content-length'];
                }
                if ($httpCode == 200) {
                    $podcast = constructPodcast($date, $url, $length, $hash['otherName']);
                    $memcache->set($cacheKey, $podcast, MEMCACHE_COMPRESSED, LONG_EXPIRY);
                } else {
                    // we cache as well if link is not found
                    $memcache->set($cacheKey, '', MEMCACHE_COMPRESSED, LONG_EXPIRY);
                }
            }
            if ($podcast) {
                $podcasts[] = $podcast;
            }
            $date->modify($decrementString);
        }

        return $podcasts;
    }


    global $CHANNELS;

    $responses = [];

    if (!isset($getChannel)) {
        foreach ($CHANNELS as $key => $value) {
            $responses = array_merge($responses, channelResponse($value));
        }
    } else {
        if (strpos($getChannel, '+')) {
            $requestChannels = explode('+', $getChannel);

            foreach ($requestChannels as $channel) {
                $responses = array_merge($responses, channelResponse($CHANNELS[$channel]));
            }
        } else if (array_key_exists($getChannel, $CHANNELS)) {
            $responses = array_merge($responses, channelResponse($CHANNELS[$getChannel]));
        }
    }

    return $responses;
}
