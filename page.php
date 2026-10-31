<?php
/**
 * 独立页面
 */
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
$this->need('header.php');
?>

<article class="piece is-page">
    <div class="piece-head">
        <div class="piece-heading">
            <h1 class="piece-title"><?php $this->title(); ?></h1>
            <?php if ($this->date): ?>
                <p class="piece-byline"><span><?php $this->date('Y-m-d'); ?></span></p>
            <?php endif; ?>
        </div>
    </div>
    <div class="prose">
        <?php yishun_content($this); ?>
    </div>
</article>

<?php if ($this->allow('comment')) $this->need('comments.php'); ?>

<?php $this->need('footer.php'); ?>
