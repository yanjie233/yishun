<?php
/**
 * 一瞬，一页纸上的博客。
 *
 * 纸色底、墨色字、一枚日期印章，没有卡片也没有阴影。正文单栏，靠字号与留白分层；
 * 昼夜两套配色一键切换，播放器、提示框、代码块都收在同一套设计语汇里。
 *
 * @package 一瞬
 * @author yanjie233
 * @version 0.1.0
 * @link https://github.com/yanjie233/yishun
 * @since 1.3.0
 */

/*
 * 列表页（首页 / 分类 / 标签 / 搜索 / 归档 / 作者）
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<?php if (!$this->is('index')): ?>
    <div class="lede">
        <p class="lede-kicker"><?php
            if ($this->is('category')) _e('分类');
            elseif ($this->is('tag')) _e('标签');
            elseif ($this->is('search')) _e('搜索');
            elseif ($this->is('author')) _e('作者');
            else _e('归档');
        ?></p>
        <h1 class="lede-title"><?php $this->archiveTitle([
            'category' => '%s',
            'search'   => '%s',
            'tag'      => '%s',
            'author'   => '%s',
        ], '', ''); ?></h1>
    </div>
<?php endif; ?>

<?php if ($this->have()): ?>
    <div class="log">
    <?php while ($this->next()): ?>
        <?php $d = yishun_date_parts($this); ?>
        <article class="entry">
            <div class="entry-gutter">
                <time class="stamp" datetime="<?php echo $d['iso']; ?>">
                    <span class="stamp-month"><?php echo $d['month']; ?></span>
                    <span class="stamp-day"><?php echo $d['day']; ?></span>
                    <span class="stamp-year"><?php echo $d['year']; ?></span>
                </time>
            </div>
            <div class="entry-body">
                <h2 class="entry-title"><a href="<?php $this->permalink() ?>"><?php $this->title() ?></a></h2>
                <div class="entry-excerpt"><?php yishun_excerpt($this, 150); ?></div>
                <?php yishun_meta($this, $this->options->showReading !== '0'); ?>
            </div>
        </article>
    <?php endwhile; ?>
    </div>

    <nav class="pager">
        <?php $this->pageNav('← 更新', '更早 →', 1, '…'); ?>
    </nav>
<?php else: ?>
    <div class="empty">
        <p>这里还什么都没有。</p>
        <p class="empty-hint">换个关键词，或者回<a href="<?php $this->options->siteUrl(); ?>">最近的文章</a>看看。</p>
    </div>
<?php endif; ?>

<?php $this->need('footer.php'); ?>
