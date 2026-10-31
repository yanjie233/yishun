<?php
/**
 * 网易云歌曲解析：/usr/themes/yishun/music.php?id=<歌曲ID>
 *
 * 数据源是自建的 NeteaseCloudMusicApi（https://music.yanjie233.top）。
 * 返回 {"ok":true,"name":..,"artist":..,"cover":..,"url":..,"duration":..,"trial":..,"cached":..}
 * 或 {"ok":false,"reason":".."}。
 *
 * 为了不让每次打开文章都等接口，做了三件事：
 *  1. 结果落盘缓存：元信息 7 天，播放地址 8 分钟（网易直链本身约 20 分钟过期）。
 *  2. 没命中时，元信息和播放地址用 curl_multi 并发取，冷启动是「较慢那个」而不是两者之和。
 *  3. 都取完才输出，响应头带 X-Music-Cache: hit|miss，方便判断是不是缓存生效。
 *
 * 两个老坑还在：
 *  - 不带 realIP 会一律返回「亲爱的,暂无版权」（按出口 IP 判地区）。
 *  - 接口给的 CDN 是 http://m*.music.126.net/...，https 页面要换成 https。
 */

$root = dirname(__DIR__, 4);
require $root . '/config.inc.php';

/* 接口地址可在主题设置里改，留空就用作者默认的公共实例 */
$api = rtrim(trim((string) (\Widget\Options::alloc()->neteaseApi ?? '')), '/');
if ($api === '') {
    $api = 'https://music.yanjie233.top';
}

$id = preg_replace('/\D/', '', (string) ($_GET['id'] ?? ''));

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=300');

if ($id === '') {
    echo json_encode(['ok' => false, 'reason' => 'missing id']);
    exit;
}

$options = \Widget\Options::alloc();
$realIp = trim((string) ($options->neteaseRealIp ?? ''));
if ($realIp === '') {
    $realIp = '116.25.146.177';
}
$cookie = trim((string) ($options->neteaseCookie ?? ''));

/* 缓存目录：优先站点内的 usr/cache/music（open_basedir 允许、不会被系统清掉），退到 /tmp */
$cacheDir = '';
foreach ([$root . '/usr/cache/music', sys_get_temp_dir()] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    if (is_dir($dir) && is_writable($dir)) {
        $cacheDir = $dir;
        break;
    }
}
if ($cacheDir === '') {
    $cacheDir = sys_get_temp_dir();
}

$ttlDetail = 7 * 86400;
$ttlUrl = 480;

function cache_read($dir, $key, $ttl)
{
    $file = $dir . '/' . md5($key) . '.json';
    if (!is_file($file) || filemtime($file) + $ttl < time()) {
        return null;
    }
    $data = json_decode((string) file_get_contents($file), true);

    return is_array($data) ? $data : null;
}

function cache_write($dir, $key, array $data)
{
    $file = $dir . '/' . md5($key) . '.json';
    $tmp = $file . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) {
        @rename($tmp, $file);
    }
}

/* 并发请求多个 URL，返回 [key => 解析后的 JSON|null] */
function fetch_many(array $urls, $timeout = 6)
{
    $result = [];
    $handles = [];
    $multi = curl_multi_init();

    foreach ($urls as $key => $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; yishun-theme)',
            CURLOPT_TCP_FASTOPEN => true,
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[$key] = $ch;
    }

    do {
        $status = curl_multi_exec($multi, $running);
        if ($running) {
            curl_multi_select($multi, 0.2);
        }
    } while ($running && $status === CURLM_OK);

    foreach ($handles as $key => $ch) {
        $body = curl_multi_getcontent($ch);
        $result[$key] = is_string($body) ? json_decode($body, true) : null;
        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
    }

    curl_multi_close($multi);

    return $result;
}

/* 一次单发请求，给重试用 */
function fetch_one($url, $timeout = 8)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; workbench-theme)',
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    return is_string($body) ? json_decode($body, true) : null;
}

$detailKey = 'detail-' . $id;
$urlKey = 'url-' . $id . '-' . $realIp . '-' . md5($cookie);

$detail = cache_read($cacheDir, $detailKey, $ttlDetail);
$play = cache_read($cacheDir, $urlKey, $ttlUrl);
$cached = ($detail !== null && $play !== null);

$urlOf = static function (string $level) use ($api, $id, $realIp, $cookie): string {
    return $api . '/song/url/v1?id=' . $id . '&level=' . $level . '&realIP=' . rawurlencode($realIp)
        . ($cookie !== '' ? '&cookie=' . rawurlencode($cookie) : '');
};

if (!$cached) {
    $want = [];
    if ($detail === null) {
        $want['detail'] = $api . '/song/detail?ids=' . $id;
    }
    if ($play === null) {
        $want['url'] = $urlOf('exhigh');
    }

    $fetched = fetch_many($want);

    if (isset($fetched['detail']) && is_array($fetched['detail']) && !empty($fetched['detail']['songs'][0])) {
        $detail = $fetched['detail'];
        cache_write($cacheDir, $detailKey, $detail);
    }
    if (isset($fetched['url']) && is_array($fetched['url']) && !empty($fetched['url']['data'][0]['url'])) {
        $play = $fetched['url'];
        cache_write($cacheDir, $urlKey, $play);
    }

    /* 网易接口偶尔会抽风返回 url:null（尤其高峰时段），换档位再试两次 */
    if (empty($play['data'][0]['url'])) {
        foreach (['exhigh', 'standard'] as $level) {
            usleep(250000);
            $retry = fetch_one($urlOf($level));
            if (!empty($retry['data'][0]['url'])) {
                $play = $retry;
                cache_write($cacheDir, $urlKey, $play);
                break;
            }
        }
    }
}

header('X-Music-Cache: ' . ($cached ? 'hit' : 'miss'));

$song = $detail['songs'][0] ?? null;
$track = is_array($play) ? ($play['data'][0] ?? []) : [];
$url = (string) ($track['url'] ?? '');

if (!$song) {
    echo json_encode(['ok' => false, 'reason' => 'detail failed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$name = (string) ($song['name'] ?? '');
$artist = implode(' / ', array_map(static fn($a) => (string) ($a['name'] ?? ''), $song['ar'] ?? []));
$cover = (string) ($song['al']['picUrl'] ?? '');
$duration = ((int) ($song['dt'] ?? 0)) / 1000;

/* VIP 曲目在没登录时只给一段试听，长度写在 freeTrialInfo.end 里 */
$trial = (int) ($track['freeTrialInfo']['end'] ?? 0);
if ($trial > 0) {
    $duration = $trial;
}

if ($url === '') {
    echo json_encode([
        'ok' => false,
        'name' => $name,
        'artist' => $artist,
        'cover' => $cover,
        'duration' => $duration,
        'reason' => 'no url',
        'cached' => $cached,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$url = preg_replace('#^http://#i', 'https://', $url);

echo json_encode([
    'ok' => true,
    'name' => $name,
    'artist' => $artist,
    'cover' => $cover,
    'url' => $url,
    'duration' => $duration,
    'trial' => $trial,
    'cached' => $cached,
], JSON_UNESCAPED_UNICODE);
