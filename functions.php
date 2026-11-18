<?php
/**
 * 一瞬：主题逻辑
 *
 * 这里只放排版需要的东西：阅读时长、日期印章的拆分、摘要、提示框与媒体短代码的渲染、
 * 以及主题设置项。模板按 Typecho 的约定分层——index / archive / category / tag /
 * author / date / search / post / page / 404 / comments，插件通过 $this->header()
 * 和 $this->footer() 接入，不针对某个插件做特判。
 *
 * @package 一瞬
 * @author yanjie233
 * @version 0.1.1
 * @link https://github.com/yanjie233/yishun
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/* ============================ 主题设置 ============================ */

function themeConfig($form)
{
    $avatar = new \Typecho\Widget\Helper\Form\Element\Text(
        'avatar',
        null,
        '',
        _t('头像地址'),
        _t('填一个图片 URL，显示在站点名左边；留空则用管理员邮箱的 Cravatar 头像')
    );
    $form->addInput($avatar);

    $footerNote = new \Typecho\Widget\Helper\Form\Element\Text(
        'footerNote',
        null,
        '',
        _t('页脚补充说明'),
        _t('显示在版权行下面的一行小字，留空则不显示')
    );
    $form->addInput($footerNote);

    $tagline = new \Typecho\Widget\Helper\Form\Element\Text(
        'tagline',
        null,
        '',
        _t('站点副标题'),
        _t('填了会接在首页标题后面（站点名 · 副标题），也让偏短的归档页标题更完整')
    );
    $form->addInput($tagline);

    $cnMonth = new \Typecho\Widget\Helper\Form\Element\Radio(
        'cnMonth',
        ['1' => _t('中文（五月）'), '0' => _t('英文缩写（MAY）')],
        '0',
        _t('月份写法'),
        _t('日期印章里的月份用中文还是英文缩写')
    );
    $form->addInput($cnMonth);

    $showReading = new \Typecho\Widget\Helper\Form\Element\Radio(
        'showReading',
        ['1' => _t('显示'), '0' => _t('隐藏')],
        '1',
        _t('显示阅读时长'),
        _t('按正文字数估算，中文约 400 字/分钟')
    );
    $form->addInput($showReading);

    $showViews = new \Typecho\Widget\Helper\Form\Element\Radio(
        'showViews',
        ['1' => _t('显示'), '0' => _t('隐藏')],
        '1',
        _t('显示阅读量'),
        _t('按单篇文章的真实访问累计，同一浏览器同一场会话只记一次；关掉后模板不输出也不计数')
    );
    $form->addInput($showViews);

    $icp = new \Typecho\Widget\Helper\Form\Element\Text(
        'icp',
        null,
        '',
        _t('备案号'),
        _t('例如 粤ICP备12345678号，填了会显示在页脚并链接到 beian.miit.gov.cn')
    );
    $form->addInput($icp);

    $icpUrl = new \Typecho\Widget\Helper\Form\Element\Text(
        'icpUrl',
        null,
        'https://beian.miit.gov.cn/',
        _t('备案号链接'),
        _t('留空则用默认的工信部备案系统地址')
    );
    $form->addInput($icpUrl);

    $neteaseApi = new \Typecho\Widget\Helper\Form\Element\Text(
        'neteaseApi',
        null,
        'https://music.yanjie233.top',
        _t('网易云接口地址'),
        _t('自建的 NeteaseCloudMusicApi 地址，末尾不要带斜杠；留空则播放器只支持本地音频')
    );
    $form->addInput($neteaseApi);

    $neteaseRealIp = new \Typecho\Widget\Helper\Form\Element\Text(
        'neteaseRealIp',
        null,
        '116.25.146.177',
        _t('网易云接口的 realIP'),
        _t('歌曲地址接口要带一个国内 IP 才不返回「暂无版权」，留空用默认值')
    );
    $form->addInput($neteaseRealIp);

    $neteaseCookie = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'neteaseCookie',
        null,
        '',
        _t('网易云 Cookie（可选）'),
        _t('不用手抓：打开 /usr/themes/yishun/music-login.php 用手机号登录，会自动写到这里；留空只取免费曲目')
    );
    $form->addInput($neteaseCookie);

    $police = new \Typecho\Widget\Helper\Form\Element\Text(
        'police',
        null,
        '',
        _t('公安备案号'),
        _t('例如 粤公网安备 44030002000001号，可留空')
    );
    $form->addInput($police);
}

/** 每篇文章的自定义字段（Typecho 的 themeFields 约定） */
function themeFields($layout)
{
    $abstract = new \Typecho\Widget\Helper\Form\Element\Textarea(
        'post_abstract',
        null,
        '',
        _t('文章摘要'),
        _t('填了就代替自动截取，显示在列表和搜索结果里')
    );
    $layout->addItem($abstract);
}

/**
 * 只给「文章」加 AI 标识开关（Typecho 的 themePostFields 钩子，独立页面不会出现）。
 * 这是主题默认字段，每次保存都会提交，不会被 applyFields() 当成脏字段删掉。
 */
function themePostFields($layout)
{
    $ai = new \Typecho\Widget\Helper\Form\Element\Radio(
        'ai_declare',
        [
            'off'     => _t('关闭提示'),
            'full'    => _t('本文由人工智能生成'),
            'partial' => _t('本文部分由人工智能生成'),
        ],
        'off',
        _t('AI 生成标识'),
        _t('「本文由人工智能生成」在正文前标注、列表页显示机器人图标；「本文部分由人工智能生成」在正文后标注、列表页显示半人半机器人图标')
    );
    $layout->addItem($ai);
}

/* ============================ AI 生成标识 ============================ */

/**
 * 读一篇文章的 AI 标识：off / full（全文生成）/ partial（部分生成）。
 * 存在主题默认字段 ai_declare 里；字段缺失（老文章）一律当 off。
 */
function yishun_ai_mode($archive)
{
    try {
        $value = (string) ($archive->fields->ai_declare ?? '');
    } catch (\Throwable $e) {
        return 'off';
    }
    return in_array($value, ['full', 'partial'], true) ? $value : 'off';
}

/** 机器人图标：全文生成 */
function yishun_ai_icon_robot()
{
    return '<svg class="ai-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" '
        . 'stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        /* 天线 */
        . '<path d="M10 5.6V3.4"/><circle cx="10" cy="2.6" r=".9"/>'
        /* 头 */
        . '<rect x="3.9" y="5.6" width="12.2" height="10.2" rx="3"/>'
        /* 眼 + 嘴 */
        . '<circle cx="7.5" cy="10.2" r="1.1"/><circle cx="12.5" cy="10.2" r="1.1"/>'
        . '<path d="M8.2 13.1h3.6"/>'
        . '</svg>';
}

/** 半人半机器人图标：部分生成（左半染色+人眼=人，中缝+右半方眼与直天线=机器） */
function yishun_ai_icon_half()
{
    return '<svg class="ai-icon ai-icon-half" viewBox="0 0 20 20" fill="none" stroke="currentColor" '
        . 'stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        /* 头（圆头，和机器人的圆角方头靠轮廓就能分开） */
        . '<circle cx="10" cy="10.6" r="6.3"/>'
        /* 左半染色 + 中缝：13px 下「一半有东西」比纯线框好认 */
        . '<path d="M10 4.3a6.3 6.3 0 0 0 0 12.6z" fill="currentColor" fill-opacity=".55" stroke="none"/>'
        . '<path d="M10 4.4v12.4" stroke-width="1.3"/>'
        /* 人眼（圆）+ 机器眼（方），左右各一只 */
        . '<circle cx="6.7" cy="9.8" r="1.05" stroke-width="1.25"/>'
        . '<rect x="11.9" y="8.8" width="2.4" height="2.4" rx=".45" stroke-width="1.25"/>'
        /* 直天线：斜天线在 13px 下会被看成水果梗 */
        . '<path d="M14.4 5.4V3.6" stroke-width="1.25"/>'
        . '</svg>';
}

/** 按模式取图标 */
function yishun_ai_icon($mode)
{
    if ('full' === $mode) {
        return yishun_ai_icon_robot();
    }
    if ('partial' === $mode) {
        return yishun_ai_icon_half();
    }
    return '';
}

/** 文章页的提示文案：全文生成在正文前，部分生成在正文后 */
function yishun_ai_notice_text($mode)
{
    if ('full' === $mode) {
        return '本文使用人工智能生成并发布';
    }
    if ('partial' === $mode) {
        return '本文部分内容由人工智能生成';
    }
    return '';
}

/** 列表页图标上的说明文字（悬停 / 读屏） */
function yishun_ai_label($mode)
{
    if ('full' === $mode) {
        return '本文由人工智能生成';
    }
    if ('partial' === $mode) {
        return '本文部分由人工智能生成';
    }
    return '';
}

/** 正文前的提示（全文生成）；正文后的提示（部分生成）由模板直接调用 */
function yishun_ai_notice($mode, $position)
{
    if ('full' !== $mode || 'before' !== $position) {
        if ('partial' !== $mode || 'after' !== $position) {
            return;
        }
    }

    echo '<p class="ai-notice ai-notice-' . ($position === 'before' ? 'full' : 'partial') . '">'
        . yishun_ai_icon($mode)
        . '<span>' . htmlspecialchars(yishun_ai_notice_text($mode), ENT_QUOTES, 'UTF-8') . '</span>'
        . '</p>';
}

/* ============================ 阅读量 ============================ */

/**
 * 阅读量存在主题自己的表里，不写进 typecho_fields。
 *
 * 为什么不能用自定义字段：Typecho 保存文章时会走 EditTrait::applyFields()，
 * 表单没提交的字段一律删除，阅读量一存进 table.fields 就会在编辑文章时被清零。
 *
 * 表结构（建表语句见 README）：
 *   cid int unsigned PRIMARY KEY, views int unsigned DEFAULT 0, updated int unsigned DEFAULT 0
 */

/** 表在不在（结果缓存到本次请求；缺表时按需建一次，缺权限就整站静默降级） */
function yishun_views_available()
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }

    $db = \Typecho\Db::get();
    try {
        $db->fetchRow($db->select()->from('table.postviews')->limit(1));
        return $ok = true;
    } catch (\Throwable $e) {
        /* 别处新装主题时表还不存在，顺手建一张；没建表权限就当功能不存在 */
    }

    try {
        $db->query(
            'CREATE TABLE IF NOT EXISTS ' . $db->getPrefix() . 'postviews ('
            . 'cid int(10) unsigned NOT NULL,'
            . 'views int(10) unsigned NOT NULL DEFAULT 0,'
            . 'updated int(10) unsigned NOT NULL DEFAULT 0,'
            . 'PRIMARY KEY (cid)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        $db->fetchRow($db->select()->from('table.postviews')->limit(1));
        return $ok = true;
    } catch (\Throwable $e) {
        return $ok = false;
    }
}

/** 主题设置里是否开启（默认开） */
function yishun_views_enabled()
{
    try {
        return ((string) (\Widget\Options::alloc()->showViews ?? '1')) !== '0';
    } catch (\Throwable $e) {
        return true;
    }
}

/** 记一次阅读：单篇文章、GET、已发布、非后台预览，同一浏览器同一会话只记一次 */
function yishun_count_view($archive)
{
    if (!yishun_views_enabled() || !yishun_views_available()) {
        return;
    }

    /* 后台预览 / 编辑器里的预览页不算 */
    if (defined('__TYPECHO_ADMIN__')) {
        return;
    }

    if (($archive->type ?? '') !== 'post' || ($archive->status ?? '') !== 'publish') {
        return;
    }

    /* 只算一次正常浏览：POST（密码解锁表单等）、HEAD（监控探活）都不算 */
    try {
        if (!\Typecho\Request::getInstance()->isGet()) {
            return;
        }
    } catch (\Throwable $e) {
        return;
    }

    if (yishun_view_is_bot()) {
        return;
    }

    $cid = (int) $archive->cid;
    if ($cid <= 0) {
        return;
    }

    if (yishun_view_seen($cid)) {
        return;
    }
    yishun_view_remember($cid);

    try {
        $db = \Typecho\Db::get();
        $now = time();
        $db->query(
            'INSERT INTO ' . $db->getPrefix() . "postviews (cid, views, updated) VALUES ({$cid}, 1, {$now})"
            . " ON DUPLICATE KEY UPDATE views = views + 1, updated = {$now}"
        );
    } catch (\Throwable $e) {
        /* 记不上就当没记，页面照常输出 */
        return;
    }

    /* 刚加过 1，模板里读到的就是这个新值 */
    yishun_views_cache($cid, null);
}

/** 爬虫/预取/预览链接不算阅读：UA 黑名单 + Sec-Purpose: prefetch */
function yishun_view_is_bot()
{
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    /* 正常浏览器一定带 UA；空 UA 基本都是脚本 */
    if ($ua === '') {
        return true;
    }
    if (preg_match(
        '/bot|crawl|spider|slurp|archiver|facebookexternalhit|embedly|quora link preview|'
        . 'headless|phantom|puppeteer|playwright|lighthouse|pingdom|uptime|monitor|'
        . 'curl|wget|python-requests|go-http-client|okhttp|java\/|axios|node-fetch|'
        . 'feedfetcher|rss|preview|scrape|validator|checker/',
        $ua
    )) {
        return true;
    }

    /* Chrome 的预取/预渲染，以及链接预览机器人 */
    foreach (['HTTP_SEC_PURPOSE', 'HTTP_PURPOSE', 'HTTP_X_PURPOSE', 'HTTP_X_MOZ'] as $key) {
        $val = strtolower((string) ($_SERVER[$key] ?? ''));
        if ($val !== '' && (str_contains($val, 'prefetch') || str_contains($val, 'prerender') || str_contains($val, 'preview'))) {
            return true;
        }
    }

    return false;
}

/** 本会话是否已记过这篇（cookie 里存 cid 列表，最多 30 个） */
function yishun_view_seen($cid)
{
    $seen = (string) \Typecho\Cookie::get('views_read', '');
    if ($seen === '') {
        return false;
    }
    return in_array((int) $cid, array_map('intval', explode(',', $seen)), true);
}

function yishun_view_remember($cid)
{
    $seen = (string) \Typecho\Cookie::get('views_read', '');
    $list = $seen === '' ? [] : array_map('intval', explode(',', $seen));
    $list[] = (int) $cid;
    $list = array_slice(array_values(array_unique($list)), -30);
    \Typecho\Cookie::set('views_read', implode(',', $list));
}

/**
 * 读一个 cid 的阅读量；表缺失或出错返回 null（模板据此整段不输出）。
 * 传 $value 为 int 时写入缓存，传 null 只清缓存。
 */
function yishun_views_cache($cid, $value = false)
{
    static $cache = [];
    $cid = (int) $cid;

    if ($value === null) {
        unset($cache[$cid]);
        return null;
    }
    if ($value !== false) {
        return $cache[$cid] = (int) $value;
    }
    return $cache[$cid] ?? false;
}

function yishun_get_views($cid)
{
    $cid = (int) $cid;
    if ($cid <= 0) {
        return null;
    }

    $cached = yishun_views_cache($cid);
    if ($cached !== false) {
        return $cached;
    }

    if (!yishun_views_available()) {
        return null;
    }

    try {
        $db = \Typecho\Db::get();
        $row = $db->fetchRow($db->select('views')->from('table.postviews')->where('cid = ?', $cid)->limit(1));
    } catch (\Throwable $e) {
        return null;
    }

    return yishun_views_cache($cid, $row ? (int) $row['views'] : 0);
}

/** 数字转人话：0 = 还没有人读过，过万用「万」 */
function yishun_views_text($views)
{
    if (!is_int($views) || $views <= 0) {
        return '还没有人读过';
    }
    if ($views >= 10000) {
        return rtrim(rtrim(number_format($views / 10000, 1, '.', ''), '0'), '.') . ' 万次阅读';
    }
    return $views . ' 次阅读';
}

/** 眼睛图标：和提示框那套线条图标同规格（20 格、1.6 描边、圆头），跟着字号缩放 */
function yishun_views_icon()
{
    return '<svg class="views-icon" viewBox="0 0 20 20" fill="none" stroke="currentColor" '
        . 'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
        . '<path d="M1.7 10S5 4.5 10 4.5 18.3 10 18.3 10 15 15.5 10 15.5 1.7 10 1.7 10z"/>'
        . '<circle cx="10" cy="10" r="2.6"/></svg>';
}

/* ============================ 基础工具 ============================ */

function yishun_reading_time($text)
{
    $plain = trim(strip_tags((string) $text));
    if ($plain === '') {
        return 1;
    }
    $cjk = preg_match_all('/[\x{4e00}-\x{9fa5}]/u', $plain);
    $words = str_word_count(preg_replace('/[\x{4e00}-\x{9fa5}]/u', ' ', $plain));
    $minutes = (int) ceil(($cjk / 400) + ($words / 220));
    return max(1, $minutes);
}

function yishun_date_parts($archive)
{
    /* Widget 的 date() 是 echo，取属性里的 Date 对象才拿得到值 */
    $d = $archive->date;

    /* 月份：默认英文缩写（MAY），主题设置里可以换成中文（五月） */
    $cn = false;
    try {
        $cn = ((string) (\Widget\Options::alloc()->cnMonth ?? '')) === '1';
    } catch (\Throwable $e) {
        $cn = false;
    }
    $cnMonths = ['一月', '二月', '三月', '四月', '五月', '六月', '七月', '八月', '九月', '十月', '十一月', '十二月'];
    $month = $cn ? $cnMonths[(int) $d->format('n') - 1] : $d->format('M');

    return [
        'day'   => $d->format('d'),
        'month' => $month,
        'year'  => $d->format('Y'),
        'full'  => $d->format('Y-m-d'),
        'iso'   => $d->format('c'),
    ];
}

/** 摘要：优先用自定义字段，其次压缩正文并去掉短代码记号 */
function yishun_text($archive, $length = 140)
{
    $fields = $archive->fields;
    $text = '';

    if (!empty($fields->post_abstract)) {
        $text = (string) $fields->post_abstract;
    } else {
        $text = strip_tags((string) $archive->excerpt);
    }

    $text = preg_replace('/\{[^}]{0,240}\}/u', '', $text);
    $text = trim(preg_replace('/\s+/u', ' ', (string) $text));

    if (mb_strlen($text, 'UTF-8') > $length) {
        $text = mb_substr($text, 0, $length, 'UTF-8') . '…';
    }

    return $text;
}

/** 摘要：模板里直接 echo */
function yishun_excerpt($archive, $length = 150)
{
    $fields = $archive->fields;
    if (!empty($fields->post_abstract)) {
        echo htmlspecialchars(trim($fields->post_abstract), ENT_QUOTES, 'UTF-8');
        return;
    }

    $text = strip_tags((string) $archive->excerpt);
    $text = preg_replace('/\{[^}]{0,240}\}/u', '', $text);
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if (mb_strlen($text, 'UTF-8') > $length) {
        $text = mb_substr($text, 0, $length, 'UTF-8') . '…';
    }
    echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function yishun_meta(\Widget\Archive $archive, $withReading = true)
{
    $minutes = $withReading ? yishun_reading_time($archive->text) : 0;
    /* 表缺失或设置里关掉时是 null，整段不输出 */
    $views = yishun_views_enabled() ? yishun_get_views($archive->cid) : null;
    ?>
    <?php
    /* 分类为空时不要留下开头的分隔点 */
    ob_start();
    $archive->category('、');
    $cats = trim(ob_get_clean());
    ?>
    <p class="entry-meta">
        <?php if ($cats !== ''): ?>
            <span class="entry-cat"><?php echo $cats; ?></span>
            <span class="entry-dot" aria-hidden="true"></span>
        <?php endif; ?>
        <?php if ($minutes): ?>
            <span class="entry-time"><?php echo $minutes; ?> 分钟读完</span>
            <span class="entry-dot" aria-hidden="true"></span>
        <?php endif; ?>
        <?php if ($views !== null): ?>
            <span class="entry-views"><?php echo yishun_views_icon(); ?><?php echo htmlspecialchars(yishun_views_text($views), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="entry-dot" aria-hidden="true"></span>
        <?php endif; ?>
        <a class="entry-comments" href="<?php $archive->permalink() ?>#comments"><?php $archive->commentsNum('没有评论', '1 条评论', '%d 条评论'); ?></a>
        <?php
        /* AI 标识：放在评论数后面，只给个图标，全文生成是机器人、部分生成是半人半机 */
        $aiMode = yishun_ai_mode($archive);
        if ($aiMode !== 'off'):
        ?>
            <span class="entry-dot" aria-hidden="true"></span>
            <span class="entry-ai" role="img"
                  aria-label="<?php echo htmlspecialchars(yishun_ai_label($aiMode), ENT_QUOTES, 'UTF-8'); ?>"
                  title="<?php echo htmlspecialchars(yishun_ai_label($aiMode), ENT_QUOTES, 'UTF-8'); ?>"><?php echo yishun_ai_icon($aiMode); ?></span>
        <?php endif; ?>
    </p>
    <?php
}

/** 头像：主题设置优先，其次管理员邮箱的 Cravatar */
function yishun_avatar()
{
    $options = \Widget\Options::alloc();
    $url = $options->avatar ?? '';
    if (!empty($url)) {
        return $url;
    }

    $db = \Typecho\Db::get();
    $user = $db->fetchRow($db->select('mail')->from('table.users')->where('uid = ?', 1)->limit(1));
    $mail = $user['mail'] ?? '';
    if (empty($mail)) {
        return '';
    }

    return 'https://cravatar.cn/avatar/' . md5(strtolower(trim($mail))) . '?d=mp&s=120';
}

/* ============================ 正文短代码 ============================ */

/**
 * 把正文里的 {cat_*} 记号翻译成当前主题的样式（原来 SunnyLite 的功能），
 * 顺带给图片补懒加载。只在单篇/独立页面输出时调用。
 */

function yishun_content($archive)
{
    $content = (string) $archive->content;

    /* 提示框：标签改成图标 */
    $icons = [
        'success' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="8"/><path d="M6.4 10.2l2.4 2.4 4.8-5"/></svg>',
        'info'    => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="8"/><path d="M10 9v5"/><path d="M10 6.4v.2"/></svg>',
        'warning' => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M10 2.6l7.2 13H2.8z"/><path d="M10 7.6v4.2"/><path d="M10 14.2v.2"/></svg>',
        /* 错误：实心红底 + 挖空的叉，和另外三个空心线条图标一眼能分开 */
        'error'   => '<svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8.2" fill="currentColor"/>'
            . '<path d="M7.5 7.5l5 5M12.5 7.5l-5 5" stroke="var(--tip-error-bg)" stroke-width="1.8" stroke-linecap="round"/></svg>',
    ];

    foreach ($icons as $kind => $icon) {
        $content = preg_replace(
            '/\{cat_tips_' . $kind . '[^}]*\}([\s\S]*?)\{\/cat_tips_' . $kind . '\}/',
            /* 用 span 输出：Markdown 会把整段包进 <p>，块级 <div> 会变成非法 <p><div>，
               浏览器拆分后每个框周围多出一圈空段落，看起来就是“间隔太大” */
            '<span class="tip tip-' . $kind . '"><span class="tip-icon" aria-hidden="true">' . $icon
            . '</span><span class="tip-body">${1}</span></span>',
            $content
        );
    }

    /* 本地音乐 */
    $content = preg_replace_callback(
        '/\{cat_localmusic\s+name="([^"]*)"\s+url="([^"]*)"\s*\}/',
        function ($m) {
            /* 交给 player.js 统一渲染成播放条；用 span 以免被 Markdown 塞进 <p> 里 */
            return '<span class="aplayer" data-local="' . htmlspecialchars($m[2], ENT_QUOTES)
                . '" data-name="' . htmlspecialchars($m[1], ENT_QUOTES) . '"></span>';
        },
        $content
    );

    /* 网易云音乐 */
    $content = preg_replace_callback(
        '/\{cat_webmusic\s+name="([^"]*)"\s+id="([^"]*)"\s*\}/',
        function ($m) {
            /* 只留歌曲 ID，歌名/歌手/封面/播放地址都由 music.php 现取（自建网易云 API） */
            $id = preg_replace('/[^0-9]/', '', $m[2]);
            if ($id === '') {
                return '';
            }
            return '<span class="aplayer" data-id="' . $id . '" data-name="'
                . htmlspecialchars($m[1], ENT_QUOTES) . '"></span>';
        },
        $content
    );

    /* 视频 */
    $content = preg_replace_callback(
        '/\{cat_video\s+key="([^"]*)"\s*\}/',
        function ($m) {
            return '<figure class="media-video"><video controls preload="metadata" src="'
                . htmlspecialchars($m[1], ENT_QUOTES) . '"></video></figure>';
        },
        $content
    );

    /* B 站 */
    $content = preg_replace_callback(
        '/\{cat_bili\s+p="([^"]*)"\s+key="([^"]*)"\s*\}/',
        function ($m) {
            $src = 'https://www.bilibili.com/blackboard/html5mobileplayer.html?bvid='
                . rawurlencode($m[2]) . '&page=' . rawurlencode($m[1])
                . '&as_wide=1&danmaku=0&hasMuteButton=1';
            return '<figure class="media-video media-bili"><iframe src="' . $src
                . '" scrolling="no" frameborder="0" allowfullscreen loading="lazy"></iframe></figure>';
        },
        $content
    );

    /* 回复可见 */
    $content = yishun_reply_only($content, $archive);

    /* 图标替换（沿用 SunnyLite 的写法） */
    $content = strtr($content, ['{x}' => '✅', '{ }' => '☑️']);

    /* 图片懒加载 */
    $content = preg_replace('/<img\s+([^>]*?)src=/i', '<img $1loading="lazy" decoding="async" src=', $content);

    echo $content;
}

/** {cat_hide}…{/cat_hide}：登录或该邮箱留过言就显示，否则提示 */
function yishun_reply_only($content, $archive)
{
    /* 页面的 $archive->user 可能是 null，统一用 User 组件判断 */
    $user = \Widget\User::alloc();
    $shown = $user->hasLogin();

    if (!$shown) {
        $mail = (string) $archive->remember('mail', true);
        if (!empty($mail)) {
            $db = \Typecho\Db::get();
            $row = $db->fetchRow(
                $db->select()->from('table.comments')
                    ->where('cid = ?', $archive->cid)
                    ->where('mail = ?', $mail)
                    ->limit(1)
            );
            $shown = !empty($row);
        }
    }

    if ($shown) {
        return strtr($content, [
            '{cat_hide}'  => '<div class="reply-only">',
            '{/cat_hide}' => '</div>',
        ]);
    }

    return preg_replace(
        '/\{cat_hide[^}]*\}([\s\S]*?)\{\/cat_hide\}/',
        '<div class="reply-only is-locked">此处内容回复后可见。</div>',
        $content
    );
}
