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
 * @version 0.1.0
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
        <a class="entry-comments" href="<?php $archive->permalink() ?>#comments"><?php $archive->commentsNum('没有评论', '1 条评论', '%d 条评论'); ?></a>
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
        'error'   => '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="8"/><path d="M7.4 7.4l5.2 5.2"/><path d="M12.6 7.4l-5.2 5.2"/></svg>',
    ];

    foreach ($icons as $kind => $icon) {
        $content = preg_replace(
            '/\{cat_tips_' . $kind . '[^}]*\}([\s\S]*?)\{\/cat_tips_' . $kind . '\}/',
            '<div class="tip tip-' . $kind . '"><span class="tip-icon" aria-hidden="true">' . $icon
            . '</span><div class="tip-body">${1}</div></div>',
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
