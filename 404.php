<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<?php $GLOBALS['wbIs404'] = true; $this->need('header.php'); ?>

<div class="lost">
    <p class="lost-code">404</p>
    <p class="lost-text">这一页只存在过一瞬。</p>
    <form method="post" class="lost-search">
        <input type="search" name="s" placeholder="搜一下关键字" autofocus>
        <button type="submit">搜索</button>
    </form>
</div>

<?php $this->need('footer.php'); ?>
