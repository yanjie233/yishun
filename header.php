<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php
/* ---------------------------------------------------------------- SEO 头信息
 * 标题 / 描述 / 规范地址 / 分享卡片 / 结构化数据 都在这里算好，下面直接输出。
 * 规范地址统一用 siteUrl 的主机名并去掉查询串，避免同一篇文章多个地址被当成重复内容。
 */
$wbOptions   = $this->options;
$wbIsPost    = $this->is('post');
$wbIsPage    = $this->is('page');
$wbIsIndex   = $this->is('index');
$wbIsSearch  = $this->is('search');
$wbIs404     = !empty($GLOBALS['wbIs404']);
$wbSiteTitle = (string) $wbOptions->title;

ob_start();
$this->archiveTitle([
    'category' => _t('%s'),
    'search'   => _t('搜索：%s'),
    'tag'      => _t('#%s'),
    'author'   => _t('%s 的记录'),
], '', ' · ');
$wbPageTitle = trim(ob_get_clean());
$wbPageTitle = rtrim($wbPageTitle, ' ·');
$wbFullTitle = $wbPageTitle !== '' ? $wbPageTitle . ' · ' . $wbSiteTitle : $wbSiteTitle;

$wbPath      = parse_url((string) $this->request->getRequestUrl(), PHP_URL_PATH);
$wbCanonical = rtrim((string) $wbOptions->siteUrl, '/') . ($wbPath ?: '/');

$wbDesc = '';
if ($wbIsPost || $wbIsPage) {
    $wbDesc = yishun_text($this, 140);
}
if ($wbDesc === '') {
    $wbDesc = trim((string) $wbOptions->description);
}
if ($wbDesc === '') {
    $wbDesc = $wbSiteTitle;
}

$wbImage = '';
if (($wbIsPost || $wbIsPage) && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', (string) $this->content, $m)) {
    $wbImage = $m[1];
    if ($wbImage !== '' && $wbImage[0] === '/') {
        $wbImage = rtrim((string) $wbOptions->siteUrl, '/') . $wbImage;
    }
}
if ($wbImage === '') {
    /* 没有正文图就用站点图标：本地文件、正方形、尺寸够，比外链头像稳 */
    $wbImage = rtrim((string) $wbOptions->siteUrl, '/') . '/favicon/icon-512.png';
}

$wbAuthor = '';
try {
    $wbAuthor = trim((string) $this->author->screenName);
} catch (\Throwable $e) {
    $wbAuthor = '';
}
if ($wbAuthor === '') {
    $wbAuthor = $wbSiteTitle;
}

/* 结构化数据：文章用 BlogPosting，首页用 WebSite，其它用 WebPage */
$wbLd = [
    '@context' => 'https://schema.org',
    '@type'    => $wbIsPost ? 'BlogPosting' : ($wbIsIndex ? 'WebSite' : 'WebPage'),
    'name'     => $wbIsPost ? $wbPageTitle : $wbFullTitle,
    'headline' => $wbIsPost ? $wbPageTitle : null,
    'url'      => $wbCanonical,
    'description' => $wbDesc,
    'inLanguage' => 'zh-CN',
];

if ($wbIsPost || $wbIsPage) {
    $wbDates = yishun_date_parts($this);
    $wbLd['datePublished'] = $wbDates['iso'] ?? '';
    $wbLd['dateModified']  = $wbDates['iso'] ?? '';
    $wbLd['author'] = ['@type' => 'Person', 'name' => $wbAuthor];
    $wbLd['publisher'] = [
        '@type' => 'Organization',
        'name'  => $wbSiteTitle,
        'logo'  => [
            '@type' => 'ImageObject',
            'url'   => rtrim((string) $wbOptions->siteUrl, '/') . '/favicon/icon-192.png',
        ],
    ];
    if ($wbImage !== '') {
        $wbLd['image'] = [$wbImage];
    }
    $wbLd['mainEntityOfPage'] = ['@type' => 'WebPage', '@id' => $wbCanonical];
}

$wbLd = array_filter($wbLd, static fn($v) => $v !== null && $v !== '' && $v !== []);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="<?php $this->options->charset(); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#e9e8e2">
    <script>
    /* 先定主题再渲染，避免刷新时闪一下 */
    (function () {
        var theme = 'light';
        try {
            var saved = localStorage.getItem('yishun-theme');
            theme = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        } catch (e) {}
        document.documentElement.setAttribute('data-theme', theme);
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) { meta.setAttribute('content', theme === 'dark' ? '#15171b' : '#e9e8e2'); }
    })();
    </script>
    <title><?php echo htmlspecialchars($wbFullTitle, ENT_QUOTES); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($wbDesc, ENT_QUOTES); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($wbCanonical, ENT_QUOTES); ?>">
    <?php if ($wbIs404 || $wbIsSearch): ?>
    <meta name="robots" content="noindex, follow">
    <?php else: ?>
    <meta name="robots" content="index, follow, max-image-preview:large">
    <?php endif; ?>

    <!-- 站点图标：由站点头像生成（usr/themes/yishun/tools/make-favicon.php） -->
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/icon-32.png">
    <link rel="apple-touch-icon" href="/favicon/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest">

    <!-- 分享卡片 -->
    <meta property="og:site_name" content="<?php echo htmlspecialchars($wbSiteTitle, ENT_QUOTES); ?>">
    <meta property="og:type" content="<?php echo $wbIsPost ? 'article' : 'website'; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($wbFullTitle, ENT_QUOTES); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($wbDesc, ENT_QUOTES); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($wbCanonical, ENT_QUOTES); ?>">
    <meta property="og:locale" content="zh_CN">
    <?php if ($wbImage !== ''): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($wbImage, ENT_QUOTES); ?>">
    <?php endif; ?>
    <?php if ($wbIsPost): ?>
    <meta property="article:published_time" content="<?php echo htmlspecialchars($wbDates['iso'] ?? '', ENT_QUOTES); ?>">
    <meta property="article:author" content="<?php echo htmlspecialchars($wbAuthor, ENT_QUOTES); ?>">
    <?php endif; ?>
    <meta name="twitter:card" content="<?php echo $wbImage !== '' ? 'summary_large_image' : 'summary'; ?>">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($wbFullTitle, ENT_QUOTES); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($wbDesc, ENT_QUOTES); ?>">
    <?php if ($wbImage !== ''): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($wbImage, ENT_QUOTES); ?>">
    <?php endif; ?>

    <script type="application/ld+json"><?php
        echo json_encode($wbLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    ?></script>

    <link rel="stylesheet" href="<?php $this->options->themeUrl('style.css?v=' . @filemtime(__DIR__ . '/style.css')); ?>">
    <?php
    /* 插件与 Typecho 的 header 输出；顺手把 meta 描述里的 {cat_*} 记号擦掉 */
    ob_start();
    /* 关掉 Typecho 自带的 description / og / twitter，避免和上面重复；canonical 也重复，下面删掉 */
    $this->header('description=&keywords=&social=');
    $head = ob_get_clean();
    $head = preg_replace('#<link rel="canonical"[^>]*>\s*#i', '', (string) $head);
    $head = preg_replace_callback(
        '/(<meta[^>]+content=")([^"]*)(\")/i',
        function ($m) {
            return $m[1] . preg_replace('/\{[^}]{0,240}\}/u', '', $m[2]) . $m[3];
        },
        (string) $head
    );
    echo $head;
    ?>
</head>
<body class="<?php echo $this->is('post') || $this->is('page') ? 'is-single' : 'is-list'; ?>">

<div class="progress" id="progress" aria-hidden="true"></div>

<header class="masthead">
    <div class="masthead-inner">
        <a class="site" href="<?php $this->options->siteUrl(); ?>">
            <?php $avatar = yishun_avatar(); ?>
            <?php if ($avatar): ?>
                <img class="site-avatar" src="<?php echo htmlspecialchars($avatar, ENT_QUOTES); ?>"
                     alt="<?php $this->options->title(); ?>" width="44" height="44" loading="lazy">
            <?php endif; ?>
            <span class="site-name"><?php $this->options->title(); ?></span>
        </a>
        <nav class="nav">
            <a<?php if ($this->is('index')): ?> class="is-active"<?php endif; ?> href="<?php $this->options->siteUrl(); ?>">文章</a>
            <?php \Widget\Contents\Page\Rows::alloc()->to($pages); ?>
            <?php while ($pages->next()): ?>
                <a<?php if ($this->is('page', $pages->slug)): ?> class="is-active"<?php endif; ?>
                   href="<?php $pages->permalink(); ?>"><?php $pages->title(); ?></a>
            <?php endwhile; ?>
        </nav>
        <button class="theme-toggle" id="theme-toggle" type="button" aria-label="切换昼夜配色" aria-pressed="false">
            <svg class="icon-moon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M16.5 12.4A6.8 6.8 0 0 1 7.6 3.5 7 7 0 1 0 16.5 12.4z"/>
            </svg>
            <svg class="icon-sun" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"
                 stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="10" cy="10" r="3.6"/>
                <path d="M10 2.4v1.6M10 16v1.6M2.4 10h1.6M16 10h1.6M4.6 4.6l1.1 1.1M14.3 14.3l1.1 1.1M15.4 4.6l-1.1 1.1M5.7 14.3l-1.1 1.1"/>
            </svg>
        </button>
        <form class="find" method="post" action="<?php $this->options->siteUrl(); ?>" role="search">
            <label class="sr" for="q">搜索</label>
            <input id="q" type="search" name="s" placeholder="搜索" autocomplete="off">
        </form>
    </div>
</header>

<main class="sheet">
