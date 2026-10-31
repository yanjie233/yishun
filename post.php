<?php
/**
 * 单篇文章
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
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
                <?php if ($this->tags): ?>
                    <span class="entry-dot" aria-hidden="true"></span>
                    <span class="piece-tags"><?php $this->tags(' ', true, ''); ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="prose" itemprop="articleBody">
        <?php yishun_content($this); ?>
    </div>

    <nav class="step">
        <div class="step-prev"><?php $this->thePrev('← %s', ''); ?></div>
        <div class="step-next"><?php $this->theNext('%s →', ''); ?></div>
    </nav>
</article>

<?php $this->need('comments.php'); ?>

<?php $this->need('footer.php'); ?>
