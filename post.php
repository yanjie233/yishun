<?php
/**
 * 单篇文章
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/* 先计数，再渲染：这样正文里显示的阅读量就是含本次的新值 */
yishun_count_view($this);

$this->need('header.php');
$d = yishun_date_parts($this);
?>

<article class="piece" itemscope itemtype="http://schema.org/BlogPosting">
    <div class="piece-head">
        <time class="stamp" datetime="<?php echo $d['iso']; ?>">
            <span class="stamp-month"><?php echo $d['month']; ?></span>
            <span class="stamp-day"><?php echo $d['day']; ?></span>
            <span class="stamp-year"><?php echo $d['year']; ?></span>
        </time>
        <div class="piece-heading">
            <h1 class="piece-title" itemprop="name headline"><?php $this->title(); ?></h1>
            <p class="piece-byline">
                <span><?php $this->category('、'); ?></span>
                <?php if ($this->options->showReading !== '0'): ?>
                    <span class="entry-dot" aria-hidden="true"></span>
                    <span><?php echo yishun_reading_time($this->text); ?> 分钟读完</span>
                <?php endif; ?>
                <?php
                $views = yishun_views_enabled() ? yishun_get_views($this->cid) : null;
                if ($views !== null):
                ?>
                    <span class="entry-dot" aria-hidden="true"></span>
                    <span class="piece-views"><?php echo yishun_views_icon(); ?><?php echo htmlspecialchars(yishun_views_text($views), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
                <?php if ($this->tags): ?>
                    <span class="entry-dot" aria-hidden="true"></span>
                    <span class="piece-tags"><?php $this->tags(' ', true, ''); ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <?php yishun_ai_notice(yishun_ai_mode($this), 'before'); ?>

    <div class="prose" itemprop="articleBody">
        <?php yishun_content($this); ?>
    </div>

    <?php yishun_ai_notice(yishun_ai_mode($this), 'after'); ?>

    <nav class="step">
        <div class="step-prev"><?php $this->thePrev('← %s', ''); ?></div>
        <div class="step-next"><?php $this->theNext('%s →', ''); ?></div>
    </nav>
</article>

<?php $this->need('comments.php'); ?>

<?php $this->need('footer.php'); ?>
