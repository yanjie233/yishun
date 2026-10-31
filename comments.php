<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
<section class="talk" id="comments">
    <?php $this->comments()->to($comments); ?>

    <?php if ($comments->have()): ?>
        <h2 class="talk-title"><?php $this->commentsNum('还没有人说话', '1 条回应', '%d 条回应'); ?></h2>
        <ol class="talk-list">
            <?php $comments->listComments(); ?>
        </ol>
        <nav class="pager"><?php $comments->pageNav('← 更早', '更新 →', 1, '…'); ?></nav>
    <?php else: ?>
        <h2 class="talk-title">还没有人说话</h2>
    <?php endif; ?>

    <?php if ($this->allow('comment')): ?>
        <div class="reply" id="<?php $this->respondId(); ?>">
            <h3 class="reply-title"><?php _e('写在后面'); ?></h3>
            <p class="reply-cancel"><?php $comments->cancelReply(); ?></p>

            <form method="post" action="<?php $this->commentUrl() ?>" id="comment-form" class="reply-form">
                <?php if ($this->user->hasLogin()): ?>
                    <p class="reply-as">
                        以 <strong><?php $this->user->screenName(); ?></strong> 的身份
                        · <a href="<?php $this->options->logoutUrl(); ?>">退出</a>
                    </p>
                <?php else: ?>
                    <div class="field-row">
                        <div class="field">
                            <label for="author">称呼</label>
                            <input type="text" name="author" id="author" value="<?php $this->remember('author'); ?>" required>
                        </div>
                        <div class="field">
                            <label for="mail">邮箱<?php if ($this->options->commentsRequireMail): ?><span class="req">必填</span><?php endif; ?></label>
                            <input type="email" name="mail" id="mail" value="<?php $this->remember('mail'); ?>"<?php if ($this->options->commentsRequireMail): ?> required<?php endif; ?>>
                        </div>
                        <div class="field">
                            <label for="url">网址<?php if ($this->options->commentsRequireUrl): ?><span class="req">必填</span><?php endif; ?></label>
                            <input type="url" name="url" id="url" placeholder="https://" value="<?php $this->remember('url'); ?>"<?php if ($this->options->commentsRequireUrl): ?> required<?php endif; ?>>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="field">
                    <label for="textarea">内容</label>
                    <textarea name="text" id="textarea" rows="6" required><?php $this->remember('text'); ?></textarea>
                </div>

                <button type="submit" class="send">提交</button>
            </form>
        </div>
    <?php else: ?>
        <p class="talk-closed">评论已关闭。</p>
    <?php endif; ?>
</section>
