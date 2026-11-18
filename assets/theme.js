/* ---------------------------------------------------------------- 昼夜开关
 * 参考 Telegram 的昼夜切换：以按钮当前位置为圆心，用 View Transitions 把新旧
 * 两套配色的整页快照叠起来，夜色那层做圆形遮罩——入夜从按钮铺开，天亮缩回按钮。
 * 因为整页是一次快照，纸色、墨色、提示框、播放条会被同一条波前一起扫过。
 * 动画本体在 style.css（见「昼夜开关动画」一节），这里只算圆心、落状态。
 */
(function () {
    'use strict';

    var root = document.documentElement;
    var btn = document.getElementById('theme-toggle');
    var meta = document.querySelector('meta[name="theme-color"]');
    var calm = window.matchMedia('(prefers-reduced-motion: reduce)');
    var PAPER = { light: '#e9e8e2', dark: '#15171b' };
    var active = null;   /* 正在跑的 ViewTransition，连点时先掐掉它 */
    var fadeTimer = 0;

    function current() {
        return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function commit(next) {
        root.setAttribute('data-theme', next);
        try { localStorage.setItem('yishun-theme', next); } catch (e) {}
        if (meta) meta.setAttribute('content', PAPER[next]);
        if (btn) {
            btn.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
            btn.setAttribute('aria-label', next === 'dark' ? '切换到浅色' : '切换到深色');
        }
    }

    /* 圆心跟着按钮走，半径取到最远那个角，保证圆形一定盖满视口 */
    function mark() {
        var box = btn ? btn.getBoundingClientRect() : null;
        var x = box ? box.left + box.width / 2 : window.innerWidth / 2;
        var y = box ? box.top + box.height / 2 : window.innerHeight / 2;
        var r = Math.hypot(
            Math.max(x, window.innerWidth - x),
            Math.max(y, window.innerHeight - y)
        ) + 2;
        root.style.setProperty('--vt-x', Math.round(x) + 'px');
        root.style.setProperty('--vt-y', Math.round(y) + 'px');
        root.style.setProperty('--vt-r', Math.ceil(r) + 'px');
    }

    function stop() {
        if (active) {
            try { active.skipTransition(); } catch (e) {}
            active = null;
        }
    }

    /* 没有 View Transitions 时的退路：整页配色一起过渡 */
    function fade(next) {
        stop();
        window.clearTimeout(fadeTimer);
        root.classList.add('is-theme-fading');
        commit(next);
        fadeTimer = window.setTimeout(function () {
            root.classList.remove('is-theme-fading');
        }, 680);
    }

    function flip() {
        var next = current() === 'dark' ? 'light' : 'dark';

        if (calm.matches) {
            stop();
            commit(next);
            return;
        }
        if (typeof document.startViewTransition !== 'function') {
            fade(next);
            return;
        }

        stop();
        mark();
        var vt;
        try {
            vt = document.startViewTransition(function () { commit(next); });
        } catch (e) {
            fade(next);
            return;
        }
        active = vt;
        var done = function () { if (active === vt) active = null; };
        vt.finished.then(done, done);
    }

    commit(current());
    if (btn) {
        btn.addEventListener('click', function () { flip(); });
    }
})();
