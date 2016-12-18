<?php
/**
 * weeksOfMonth = comma separated 1-5
 */
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
        'startTime' => '2000'
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
        'day' => 'saturday',
        'weeksOfMonth' => '1',
        'startTime' => '2000'
    ],
    'bristarry' => [
        'name' => 'Bristarry Night',
        'otherName' => '布一樣的星空',
        'channel' => '4eb',
        'day' => 'saturday',
        'weeksOfMonth' => '2,3,4,5',
        'startTime' => '2000'
    ],
    'sundaymorning' => [
        'name' => 'Good Morning Sunday!',
        'otherName' => '星期日早晨',
        'channel' => '4eb',
        'day' => 'sunday',
        'startTime' => '1015'
    ],
    'trend' => [
        'name' => 'Sunday Afternoon',
        'otherName' => '客座新潮流',
        'channel' => '4eb',
        'day' => 'sunday',
        'weeksOfMonth' => '1,2,3',
        'startTime' => '1515'
    ],
    'brisafternoon' => [
        'name' => 'Sunday Afternoon',
        'otherName' => '午後的布村',
        'channel' => '4eb',
        'day' => 'sunday',
        'weeksOfMonth' => '4',
        'startTime' => '1515'
    ],
    'classic' => [
        'name' => 'Sunday Afternoon',
        'otherName' => '經典一刻',
        'channel' => '4eb',
        'day' => 'sunday',
        'weeksOfMonth' => '5',
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

function getWeekOfMonth(DateTime $date) {
    $firstDayOfMonth = new DateTime($date->format('Y-m-1'));
    return ceil((intval($firstDayOfMonth->format('N')) + intval($date->format('j')) - 1) / 7);
}

function getChannel($channel){
    global $CHANNELS;
    return $CHANNELS[$channel];
}

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
        $formattedPubTime = $dateTime->format('D d M Y H:i:s');

        return [
            'title' => $name . ' - ' . $formattedDateTime,
            'date' => $formattedDateTime,
            'm4a' => $url,
            'length' => $length,
            'pubDate' => $formattedPubTime
        ];
    }

    function channelResponse(array $hash, $rows = 15)
    {
        global $memcache;
//        $urlTemplate = 'http://media.emit.com/%s/chinese/%s%s%s%s/aac_mid.m4a';
        $urlTemplate = 'http://emit-media-production.s3.amazonaws.com/%s/chinese/%s/%s/%s/%s/%s_chinese_64.m4a';
        $decrementString = '-1 week';
        if (array_key_exists('day', $hash)) {
            $date = new DateTime('@' . strtotime('this ' . $hash['day'] . 'UTC'. $hash['startTime']));
            if ($date >= (new DateTime('now'))->modify('-1 day')) {
                $date->modify('-1 week');
            }
        } else {
            $date = new DateTime('now');
        }

        if (array_key_exists('weeksOfMonth', $hash)) {
            $weeksOfMonth = explode(',', $hash['weeksOfMonth']);
        }

        $podcasts = [];

        for ($i = 0; $i < $rows; $i++) {
            $url = vsprintf($urlTemplate, urlHelper($date, $hash));
            if ($url === $podcasts[$i - 1]['m4a']
                || (isset($weeksOfMonth) && !in_array(getWeekOfMonth($date), $weeksOfMonth))) {
                $date->modify($decrementString);
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

    if (empty($getChannel)) {
        foreach ($CHANNELS as $key => $value) {
            $responses = array_merge($responses, channelResponse($value, 1));
        }
    } else {
        if (strpos($getChannel, '_')) {
            $requestChannels = explode('_', $getChannel);

            foreach ($requestChannels as $channel) {
                $responses = array_merge($responses, channelResponse($CHANNELS[$channel]));
            }
        } else if (array_key_exists($getChannel, $CHANNELS)) {
            $responses = array_merge($responses, channelResponse($CHANNELS[$getChannel]));
        }
    }

    usort($responses, function($a,$b) {
        return strtotime($b['pubDate'])-strtotime($a['pubDate']);
    });

    return $responses;
}
