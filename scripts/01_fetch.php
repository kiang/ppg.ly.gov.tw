<?php
$basePath = dirname(__DIR__);
$rawPagePath = $basePath . '/raw/page';
$rawListFIle = $basePath . '/raw/list.html';
if (!file_exists($rawListFIle)) {
    file_put_contents($rawListFIle, file_get_contents('https://www.ly.gov.tw/Pages/List.aspx?nodeid=109'));
}
$rawList = file_get_contents($rawListFIle);
$pos = strpos($rawList, '<section id="six-legislatorListBox">');
$posEnd = strpos($rawList, '</section>', $pos);
$parts = explode('<figure>', substr($rawList, $pos, $posEnd - $pos));
$peopleParty = [];
foreach ($parts as $part) {
    $pos = strpos($part, '</figure>');
    if (false !== $pos) {
        $partyParts = explode('alt="', substr($part, 0, $pos));
        $party = substr($partyParts[1], 0, strpos($partyParts[1], '徽章'));
        if (!empty($party)) {
            $name = trim(strip_tags(substr($part, 0, $pos)));
            $peopleParty[$name] = $party;
        }
    }
}
$peopleParty['鄭天財Sra Kacaw'] = '中國國民黨';
$days = ['20240401', '20240403', '20240410', '20240411', '20240415'];
$targetPath = $basePath . '/docs/json';
if (!file_exists($targetPath)) {
    mkdir($targetPath, 0777, true);
}
$result = [];
foreach ($days as $day) {
    $dayPath = $rawPagePath . '/' . $day;
    if (!file_exists($dayPath)) {
        mkdir($dayPath, 0777, true);
    }
    $total = 1;
    $totalDone = false;
    for ($i = 1; $i <= $total; $i++) {
        $pageRawFile = $dayPath . '/' . $i . '.html';
        if (!file_exists($pageRawFile)) {
            file_put_contents($pageRawFile, file_get_contents("https://ivod.ly.gov.tw/Demand/NewsClip?Querydate={$day}&Committeename=%E5%8F%B8%E6%B3%95%E5%8F%8A%E6%B3%95%E5%88%B6%E5%A7%94%E5%93%A1%E6%9C%83&page={$i}"));
        }
        $page = file_get_contents($pageRawFile);
        if (false === $totalDone) {
            $totalDone = true;
            $parts = explode('var pageSize = "', $page);
            $pos = strpos($parts[1], '"');
            $total = intval(substr($parts[1], 0, $pos));
        }

        $pos = strpos($page, '<span id="spanDateTmp" class="number">');
        $posEnd = strpos($page, '<div id="ivod-pagination" class="pagination-sm">');
        $parts = explode('<ul id="clipUl">', substr($page, $pos, $posEnd - $pos));
        array_shift($parts);
        foreach ($parts as $part) {
            $item = [];
            $clipParts = explode('/Play/Clip/1M/', $part);
            $clipPos = strpos($clipParts[1], '"');
            $item['id'] = substr($clipParts[1], 0, $clipPos);
            $contentPos = strpos($part, '<p>委員');
            $lines = explode('</p>', substr($part, $contentPos));
            foreach ($lines as $line) {
                $cols = explode('：', trim(strip_tags($line)));
                switch ($cols[0]) {
                    case '委員':
                        $item['name'] = $cols[1];
                        break;
                    case '委員發言時間':
                        $periods = explode(' - ', $cols[1]);
                        break;
                    case '會議時間':
                        $theDate = substr($cols[1], 0, 10);
                        $item['timeBegin'] = $theDate . ' ' . $periods[0];
                        $item['timeEnd'] = $theDate . ' ' . $periods[1];
                        $item['timeUnixBegin'] = strtotime($item['timeBegin']);
                        $item['timeUnixEnd'] = strtotime($item['timeEnd']);
                        $item['seconds'] = $item['timeUnixEnd'] - $item['timeUnixBegin'];
                        break;
                }
            }
            $item['party'] = $peopleParty[$item['name']];
            if (!isset($result[$item['party']])) {
                $result[$item['party']] = [
                    'clips' => [],
                    'seconds' => 0,
                    'count' => 0,
                    'usertime' => '',
                ];
            }
            $result[$item['party']]['clips'][] = $item;
            $result[$item['party']]['seconds'] += $item['seconds'];
            $result[$item['party']]['count'] += 1;
        }
    }
}
function cmp($a, $b)
{
    return strcmp($a["timeUnixBegin"], $b["timeUnixBegin"]);
}
$total = 0;
foreach ($result as $party => $data) {
    usort($result[$party]['clips'], "cmp");
    $seconds = $data['seconds'] % 60;
    $minutes = ($data['seconds'] - $seconds) / 60 % 60;
    $hours = ($data['seconds'] - $minutes * 60 - $seconds) / 3600;
    $result[$party]['usertime'] = sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
    $total += $data['seconds'];

    $block = "{$party} - {$result[$party]['count']} - {$result[$party]['usertime']}\n---\n";
    foreach ($result[$party]['clips'] as $clip) {
        $block .= "- [{$clip['name']} {$clip['timeBegin']}](https://ivod.ly.gov.tw/Play/Clip/1M/{$clip['id']})\n";
    }
    echo $block . "\n\n";
}
$seconds = $total % 60;
$minutes = ($total - $seconds) / 60 % 60;
$hours = ($total - $minutes * 60 - $seconds) / 3600;
echo "共計發言時間： ";
echo sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
file_put_contents($targetPath . '/data.json', json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
