<?php
/**
 * 网易云账号登录（仅管理员可见）：登录成功后把 cookie 写进主题设置，
 * music.php 取播放地址时带上它，VIP 曲目就能听全曲。
 *
 * 地址：https://www.yanjie233.top/usr/themes/yishun/inc/music-login.php
 */

$root = dirname(__DIR__, 4);
require $root . '/config.inc.php';

header('Cache-Control: no-store, no-cache, must-revalidate');

/* 这个文件是直接被 nginx 执行的，没走 index.php，所以得自己把 cookie 前缀补上：
   Typecho 的 cookie 名前缀是 md5(siteUrl)，而在本页上 Options::rootUrl 会变成
   .../usr/themes/yishun，用它会算错前缀、读不到登录态。 */
\Typecho\Cookie::setPrefix(rtrim(\Widget\Options::alloc()->siteUrl, '/'));
if (defined('__TYPECHO_COOKIE_OPTIONS__')) {
    \Typecho\Cookie::setOptions(__TYPECHO_COOKIE_OPTIONS__);
}

$user = \Widget\User::alloc();

if (!$user->pass('administrator', true)) {
    http_response_code(403);
    $login = (string) \Widget\Options::alloc()->loginUrl;
    echo '<!doctype html><meta charset="utf-8"><title>需要管理员</title>'
        . '<div style="font:16px/1.8 system-ui;max-width:32rem;margin:4rem auto;padding:0 1.2rem">'
        . '<h1 style="font-size:1.2rem">这个页面只有管理员能进</h1>'
        . '<p>先登录后台，再回到这里：<a href="' . htmlspecialchars($login, ENT_QUOTES) . '">去登录</a></p>'
        . '</div>';
    exit;
}

$API = rtrim(trim((string) (\Widget\Options::alloc()->neteaseApi ?? '')), '/');
if ($API === '') {
    $API = 'https://music.yanjie233.top';
}
$db = \Typecho\Db::get();

/* ---------------- 主题设置读写（music-store.php） ---------------- */

require __DIR__ . '/music-store.php';

function music_api($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; yishun-theme)',
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = is_string($body) ? json_decode($body, true) : null;

    if (!is_array($json)) {
        return ['code' => $code, 'message' => '接口没有返回 JSON（HTTP ' . $code . '）：' . substr((string) $body, 0, 120)];
    }

    return $json;
}

/* ---------------- 处理动作 ---------------- */

$notice = null;
$noticeType = 'ok';
$cfg = music_theme_cfg($db);

$refererHost = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if ($refererHost !== null && strcasecmp($refererHost, $host) !== 0) {
    http_response_code(403);
    exit('forbidden');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $phone = preg_replace('/\D/', '', (string) ($_POST['phone'] ?? ''));

    if ($action === 'sendcode') {
        if (strlen($phone) !== 11) {
            $notice = '手机号得是 11 位';
            $noticeType = 'err';
        } else {
            $r = music_api($API . '/captcha/sent?phone=' . $phone);
            if (($r['code'] ?? 0) === 200) {
                $notice = '验证码已发送，查收短信';
            } else {
                $notice = '发送失败：' . ($r['message'] ?? $r['msg'] ?? ('code ' . ($r['code'] ?? '?')));
                $noticeType = 'err';
            }
        }
    } elseif ($action === 'login_password' || $action === 'login_captcha') {
        $query = 'phone=' . $phone;

        if ($action === 'login_password') {
            $query .= '&password=' . rawurlencode((string) ($_POST['password'] ?? ''));
        } else {
            $query .= '&captcha=' . rawurlencode((string) ($_POST['captcha'] ?? ''));
        }

        if (strlen($phone) !== 11) {
            $notice = '手机号得是 11 位';
            $noticeType = 'err';
        } else {
            $r = music_api($API . '/login/cellphone?' . $query);
            $cookie = (string) ($r['cookie'] ?? '');

            if (($r['code'] ?? 0) === 200 && $cookie !== '') {
                $cfg['neteaseCookie'] = $cookie;
                music_theme_save($db, $cfg);
                $notice = '登录成功，cookie 已保存（VIP 曲目现在可以听全曲）';
            } else {
                $notice = '登录失败：' . ($r['message'] ?? $r['msg'] ?? ('code ' . ($r['code'] ?? '?')));
                $noticeType = 'err';
            }
        }
    } elseif ($action === 'logout') {
        $cfg['neteaseCookie'] = '';
        music_theme_save($db, $cfg);
        $notice = '已清除保存的 cookie';
    }
}

$cfg = music_theme_cfg($db);
$cookie = (string) ($cfg['neteaseCookie'] ?? '');

/* 有 cookie 就查一下登录状态（不显示 cookie 本身） */
$profile = null;
if ($cookie !== '') {
    $status = music_api($API . '/login/status?cookie=' . rawurlencode($cookie));
    $data = $status['data'] ?? [];
    if (($data['code'] ?? 0) === 200 && !empty($data['profile'])) {
        $profile = $data['profile'];
    }
}

$vipMap = [0 => '无 VIP', 10 => '音乐包', 11 => '黑胶 VIP', 100 => '黑胶 VIP' . '', 110 => '黑胶 VIP+'];
$vipType = (int) ($profile['vipType'] ?? 0);

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>网易云登录 · 一瞬</title>
<style>
    :root {
        --paper: #e9e8e2; --paper-lift: #f1f0ea; --ink: #15171b; --ink-mid: #4b4f55;
        --ink-faint: #85888c; --rule: #c3c1b6; --stamp: #a63c2c;
        --serif: "Noto Serif SC", "Songti SC", Georgia, serif;
        --sans: system-ui, -apple-system, "PingFang SC", "Microsoft YaHei", sans-serif;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0; padding: 2.5rem 1.25rem;
        background: var(--paper); color: var(--ink);
        font-family: var(--sans); line-height: 1.7;
    }
    .wrap { max-width: 34rem; margin: 0 auto; }
    h1 { font-family: var(--serif); font-size: 1.5rem; margin: 0 0 0.35rem; }
    .sub { margin: 0 0 1.6rem; color: var(--ink-faint); font-size: 0.85rem; }
    .card {
        background: var(--paper-lift); border: 1px solid var(--rule);
        padding: 1.2rem 1.25rem; margin-bottom: 1rem;
    }
    .card h2 { font-size: 0.78rem; letter-spacing: 0.18em; color: var(--ink-faint); margin: 0 0 0.8rem; font-weight: 600; }
    .row { display: flex; gap: 0.6rem; margin-bottom: 0.75rem; }
    label { display: block; font-size: 0.8rem; color: var(--ink-mid); margin-bottom: 0.25rem; }
    input[type=text], input[type=password], input[type=tel] {
        width: 100%; padding: 0.5rem 0.65rem; font-size: 0.92rem;
        border: 1px solid var(--rule); background: #fff; color: var(--ink);
        font-family: inherit;
    }
    input:focus { outline: 2px solid var(--stamp); outline-offset: -1px; }
    button {
        padding: 0.5rem 1rem; font-size: 0.88rem; font-family: inherit; cursor: pointer;
        border: 1px solid var(--ink); background: var(--ink); color: var(--paper);
    }
    button.ghost { background: transparent; color: var(--ink); }
    button:disabled { opacity: 0.45; cursor: default; }
    .notice { padding: 0.65rem 0.85rem; margin-bottom: 1rem; font-size: 0.86rem; border-left: 3px solid var(--ink); background: var(--paper-lift); }
    .notice.err { border-left-color: var(--stamp); color: #8f3324; }
    .who { display: flex; align-items: center; gap: 0.8rem; }
    .who img { width: 48px; height: 48px; border: 1px solid var(--rule); object-fit: cover; }
    .who .name { font-family: var(--serif); font-weight: 600; }
    .who .meta { font-size: 0.8rem; color: var(--ink-faint); }
    .switch { display: flex; gap: 0.5rem; margin-bottom: 0.9rem; }
    .switch button { flex: 0 0 auto; }
    .switch button[aria-pressed=true] { background: var(--ink); color: var(--paper); }
    .hint { font-size: 0.76rem; color: var(--ink-faint); margin: 0.6rem 0 0; }
    a { color: var(--stamp); }
</style>
</head>
<body>
<div class="wrap">
    <h1>网易云登录</h1>
    <p class="sub">登录成功后 cookie 会存进主题设置，播放器取 VIP 曲目时自动带上。地址记一下：<code>/usr/themes/yishun/inc/music-login.php</code></p>

    <?php if ($notice): ?>
        <div class="notice <?= $noticeType === 'err' ? 'err' : '' ?>"><?= h($notice) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>当前状态</h2>
        <?php if ($profile): ?>
            <div class="who">
                <?php if (!empty($profile['avatarUrl'])): ?>
                    <img src="<?= h($profile['avatarUrl']) ?>" alt="">
                <?php endif; ?>
                <div>
                    <div class="name"><?= h($profile['nickname'] ?? '已登录') ?></div>
                    <div class="meta"><?= h($vipMap[$vipType] ?? ('vipType ' . $vipType)) ?> · uid <?= h($profile['userId'] ?? '?') ?></div>
                </div>
            </div>
            <form method="post" style="margin-top:0.9rem">
                <input type="hidden" name="action" value="logout">
                <button class="ghost" type="submit">退出并清除 cookie</button>
            </form>
        <?php elseif ($cookie !== ''): ?>
            <p style="margin:0">存着一个 cookie，但已经失效了。重新登录一次。</p>
        <?php else: ?>
            <p style="margin:0;color:var(--ink-faint)">还没登录。免费曲目能正常播，VIP 曲目只有 30 秒试听。</p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>手机号登录</h2>
        <div class="switch">
            <button type="button" id="tab-pw" aria-pressed="true">密码登录</button>
            <button type="button" id="tab-code" aria-pressed="false" class="ghost">验证码登录</button>
        </div>

        <form method="post" id="form-pw">
            <input type="hidden" name="action" value="login_password">
            <label for="phone">手机号</label>
            <input id="phone" type="tel" name="phone" maxlength="11" placeholder="11 位手机号" required>
            <label for="password" style="margin-top:0.7rem">密码</label>
            <input id="password" type="password" name="password" placeholder="网易云密码" required>
            <div style="margin-top:0.9rem"><button type="submit">登录</button></div>
        </form>

        <form method="post" id="form-code" style="display:none">
            <input type="hidden" name="action" value="login_captcha">
            <label for="phone2">手机号</label>
            <div class="row">
                <input id="phone2" type="tel" name="phone" maxlength="11" placeholder="11 位手机号" required>
                <button class="ghost" type="button" id="send-code" style="flex:0 0 auto;white-space:nowrap">发送验证码</button>
            </div>
            <label for="captcha">验证码</label>
            <input id="captcha" type="text" name="captcha" inputmode="numeric" placeholder="短信里的 6 位数字" required>
            <div style="margin-top:0.9rem"><button type="submit">登录</button></div>
        </form>

        <p class="hint">密码只用于这一次登录请求，不会写进数据库，也不写日志；存下来的只有接口返回的 cookie。</p>
    </div>
</div>
<script>
(function () {
    var pw = document.getElementById('form-pw');
    var code = document.getElementById('form-code');
    var tabPw = document.getElementById('tab-pw');
    var tabCode = document.getElementById('tab-code');

    function show(which) {
        var isPw = which === 'pw';
        pw.style.display = isPw ? '' : 'none';
        code.style.display = isPw ? 'none' : '';
        tabPw.setAttribute('aria-pressed', String(isPw));
        tabCode.setAttribute('aria-pressed', String(!isPw));
        tabPw.className = isPw ? '' : 'ghost';
        tabCode.className = isPw ? 'ghost' : '';
    }

    tabPw.addEventListener('click', function () { show('pw'); });
    tabCode.addEventListener('click', function () { show('code'); });

    /* 「发送验证码」走同一个表单，但要换成 sendcode 动作 */
    var sendBtn = document.getElementById('send-code');
    if (sendBtn) {
        sendBtn.addEventListener('click', function () {
            var form = document.getElementById('form-code');
            var phone = document.getElementById('phone2');
            if (!/^\d{11}$/.test(phone.value)) { phone.focus(); return; }
            form.querySelector('input[name="action"]').value = 'sendcode';
            form.querySelector('input[name="captcha"]').removeAttribute('required');
            form.submit();
        });
    }
})();
</script>
</body>
</html>
