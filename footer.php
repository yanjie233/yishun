<?php if (!defined('__TYPECHO_ROOT_DIR__')) exit; ?>
</main>

<footer class="colophon">
    <div class="colophon-inner">
        <p class="colophon-line">
            <span>© <?php echo date('Y'); ?> <?php $this->options->title(); ?></span>
            <span>Typecho <?php echo \Typecho\Common::VERSION; ?> · <a class="colophon-theme"
                href="https://github.com/yanjie233/yishun" target="_blank" rel="noopener">一瞬</a></span>
        </p>
        <?php if (!empty($this->options->footerNote)): ?>
            <p class="colophon-note"><?php $this->options->footerNote(); ?></p>
        <?php endif; ?>
        <?php if (!empty($this->options->icp) || !empty($this->options->police)): ?>
            <p class="colophon-legal">
                <?php if (!empty($this->options->icp)): ?>
                    <a href="<?php echo htmlspecialchars($this->options->icpUrl ?: 'https://beian.miit.gov.cn/', ENT_QUOTES); ?>"
                       target="_blank" rel="nofollow noopener"><?php $this->options->icp(); ?></a>
                <?php endif; ?>
                <?php if (!empty($this->options->icp) && !empty($this->options->police)): ?>
                    <span class="legal-sep" aria-hidden="true">·</span>
                <?php endif; ?>
                <?php if (!empty($this->options->police)): ?>
                    <span><?php $this->options->police(); ?></span>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</footer>

<?php $this->footer(); ?>
<script>window.YISHUN_THEME_URL = "<?php $this->options->themeUrl(); ?>";</script>
<script src="<?php $this->options->themeUrl('assets/player.js?v=' . @filemtime(__DIR__ . '/assets/player.js')); ?>" defer></script>
<script>
(function () {
    var bar = document.getElementById('progress');
    if (bar) {
        var frame = 0;
        var paint = function () {
            frame = 0;
            var doc = document.documentElement;
            var span = doc.scrollHeight - window.innerHeight;
            var done = span > 0 ? Math.min(1, Math.max(0, doc.scrollTop / span)) : 0;
            bar.style.transform = 'scaleX(' + done + ')';
        };
        window.addEventListener('scroll', function () {
            if (!frame) frame = requestAnimationFrame(paint);
        }, { passive: true });
        paint();
    }

    /* 昼夜开关 */
    var root = document.documentElement;
    var btn = document.getElementById('theme-toggle');
    var apply = function (theme) {
        root.setAttribute('data-theme', theme);
        try { localStorage.setItem('yishun-theme', theme); } catch (e) {}
        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.setAttribute('content', theme === 'dark' ? '#15171b' : '#e9e8e2');
        if (btn) {
            btn.setAttribute('aria-pressed', theme === 'dark' ? 'true' : 'false');
            btn.setAttribute('aria-label', theme === 'dark' ? '切换到浅色' : '切换到深色');
        }
    };
    apply(root.getAttribute('data-theme') || 'light');
    if (btn) {
        btn.addEventListener('click', function () {
            apply(root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark');
        });
    }
})();
</script>
</body>
</html>
