<?php
/**
 * 用站点头像生成 favicon / PWA 图标（命令行运行）：
 *   php usr/themes/yishun/tools/make-favicon.php
 *
 * 输出到站点根目录：
 *   favicon.ico（32×32 PNG 封装）、favicon/icon-32.png、icon-180.png、icon-192.png、icon-512.png
 * 头像来源：主题设置里的「头像地址」，留空则用管理员邮箱的 Cravatar。
 */

$root = dirname(__DIR__, 4);
require $root . '/config.inc.php';
require $root . '/usr/themes/yishun/functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('cli only');
}

$options = \Widget\Options::alloc();
$avatar = trim((string) yishun_avatar());

if ($avatar === '') {
    fwrite(STDERR, "没找到头像地址\n");
    exit(1);
}

/* Cravatar/Gravatar 支持指定尺寸，默认给的是 120px，做图标太小，这里直接要 512 */
if (preg_match('#^(https?://(?:cravatar|gravatar|secure\.gravatar)[^?]*)\?(.*)$#i', $avatar, $m)) {
    $query = $m[2];
    $query = preg_replace('/(^|&)s=\d+/', '${1}s=512', $query);
    if (strpos($query, 's=512') === false) {
        $query = 's=512&' . $query;
    }
    $avatar = $m[1] . '?' . $query;
}

echo "avatar: $avatar\n";

$ch = curl_init($avatar);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; yishun-theme favicon)',
]);
$raw = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!is_string($raw) || $raw === '' || $code >= 400) {
    fwrite(STDERR, "下载头像失败（HTTP $code）\n");
    exit(1);
}

$src = @imagecreatefromstring($raw);
if (!$src) {
    fwrite(STDERR, "头像不是可识别的图片格式\n");
    exit(1);
}

/**
 * 裁成正方形再缩放。
 * $crop 是取短边的比例（0.62 比整张更聚焦，16~32px 的标签图才认得出人脸），
 * $fx/$fy 是焦点位置（0~1），默认略偏上，人像的脸通常在中间偏上。
 */
function square_icon($src, int $size, float $crop = 0.62, float $fx = 0.5, float $fy = 0.42)
{
    $w = imagesx($src);
    $h = imagesy($src);
    $side = (int) max(1, min($w, $h) * $crop);
    $sx = (int) max(0, min($w - $side, $w * $fx - $side / 2));
    $sy = (int) max(0, min($h - $side, $h * $fy - $side / 2));

    $dst = imagecreatetruecolor($size, $size);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $side, $side);

    return $dst;
}

$iconDir = $root . '/favicon';
if (!is_dir($iconDir)) {
    mkdir($iconDir, 0755, true);
}

$sizes = [32, 180, 192, 512];
$png32 = null;

foreach ($sizes as $size) {
    $img = square_icon($src, $size);
    $path = $size === 180 ? $iconDir . '/apple-touch-icon.png' : $iconDir . '/icon-' . $size . '.png';
    imagepng($img, $path, 9);
    imagedestroy($img);

    if ($size === 32) {
        $png32 = $path;
    }
    echo "wrote $path\n";
}

/* favicon.ico：单个 32×32 的 PNG 封装（Vista 以后的浏览器都认） */
if ($png32 && is_file($png32)) {
    $png = file_get_contents($png32);
    $len = strlen($png);

    $ico = pack('vvv', 0, 1, 1);
    $ico .= pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, $len, 22);
    $ico .= $png;

    file_put_contents($root . '/favicon.ico', $ico);
    echo "wrote {$root}/favicon.ico (" . strlen($ico) . " bytes)\n";
}

/* webmanifest，顺手支持「添加到主屏幕」 */
$manifest = [
    'name' => (string) $options->title,
    'short_name' => mb_substr((string) $options->title, 0, 12),
    'icons' => [
        ['src' => '/favicon/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'],
        ['src' => '/favicon/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png'],
    ],
    'theme_color' => '#e9e8e2',
    'background_color' => '#e9e8e2',
    'display' => 'standalone',
    'start_url' => '/',
];
file_put_contents($root . '/site.webmanifest', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
echo "wrote {$root}/site.webmanifest\n";

imagedestroy($src);
