/**
 * 一瞬的播放条：把 Meting 留下的 .aplayer 占位换成横向的一条播放条。
 * 左起：封面 · 曲名/歌手 · 播放键 · 进度条 · 时间。播放用原生 <audio>，不依赖 APlayer。
 */
(function () {
    'use strict';

    var NOTE = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 4v10.3a3.4 3.4 0 1 1-2-3.1V7.6l-7 1.6v7.1a3.4 3.4 0 1 1-2-3.1V6.7l11-2.7z"/></svg>';

    function fmt(sec) {
        if (!isFinite(sec) || sec < 0) { return '0:00'; }
        var m = Math.floor(sec / 60);
        var s = Math.floor(sec % 60);
        return m + ':' + (s < 10 ? '0' + s : s);
    }

    function build(track, placeholder, opts) {
        opts = opts || {};
        var wrap = document.createElement('figure');
        wrap.className = 'track';

        var cover = document.createElement('div');
        cover.className = 'track-cover';
        if (track.cover) {
            /* 有些老专辑的 pic 接口取不到图，加载失败就退回音符占位，别留一个空方块 */
            var img = new Image();
            img.onload = function () {
                cover.style.backgroundImage = 'url("' + track.cover + '")';
            };
            img.onerror = function () {
                cover.className = 'track-cover is-empty';
                cover.innerHTML = NOTE;
            };
            img.src = track.cover;
        } else {
            cover.className = 'track-cover is-empty';
            cover.innerHTML = NOTE;
        }

        var meta = document.createElement('div');
        meta.className = 'track-meta';

        var title = document.createElement('p');
        title.className = 'track-title';
        title.textContent = track.name || '未命名曲目';
        title.title = title.textContent;

        var artist = document.createElement('p');
        artist.className = 'track-artist';
        artist.textContent = track.artist || '未知歌手';

        if (track.trial) {
            var tag = document.createElement('span');
            tag.className = 'track-tag';
            tag.textContent = '试听';
            artist.appendChild(document.createTextNode(' '));
            artist.appendChild(tag);
        }

        meta.appendChild(title);
        meta.appendChild(artist);

        var play = document.createElement('button');
        play.className = 'track-play';
        play.type = 'button';
        play.setAttribute('aria-label', '播放');
        play.innerHTML = '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M6 3.6v12.8L16 10z"/></svg>';

        var cur = document.createElement('span');
        cur.className = 'track-time';
        cur.textContent = '0:00';

        var bar = document.createElement('div');
        bar.className = 'track-bar';
        bar.setAttribute('role', 'slider');
        bar.setAttribute('aria-valuemin', '0');
        bar.setAttribute('aria-valuemax', '100');
        bar.setAttribute('aria-valuenow', '0');
        var fill = document.createElement('span');
        fill.className = 'track-fill';
        bar.appendChild(fill);

        var dur = document.createElement('span');
        dur.className = 'track-time track-dur';
        dur.textContent = track.duration ? fmt(track.duration) : '--:--';

        var controls = document.createElement('div');
        controls.className = 'track-controls';
        controls.appendChild(play);
        controls.appendChild(cur);
        controls.appendChild(bar);
        controls.appendChild(dur);

        wrap.appendChild(cover);
        wrap.appendChild(meta);

        /* 放不出来时把控制区换成一句说明，别留一个点不动的播放键 */
        function degrade() {
            if (wrap.classList.contains('is-dead')) { return; }
            wrap.classList.add('is-dead');

            if (controls.parentNode) { controls.parentNode.removeChild(controls); }

            var note = document.createElement('p');
            note.className = 'track-note';
            note.textContent = opts.msg || '这首歌暂时没有可播直链';

            if (opts.retry) {
                var again = document.createElement('button');
                again.type = 'button';
                again.className = 'track-retry';
                again.textContent = '重试';
                again.addEventListener('click', function () {
                    again.disabled = true;
                    again.textContent = '重试中…';
                    opts.retry();
                });
                note.appendChild(document.createTextNode(' · '));
                note.appendChild(again);
            }

            if (opts.link) {
                var go = document.createElement('a');
                go.href = opts.link;
                go.target = '_blank';
                go.rel = 'noopener';
                go.textContent = '去网易云听';
                note.appendChild(document.createTextNode(' · '));
                note.appendChild(go);
            }

            var body = document.createElement('div');
            body.className = 'track-note-wrap';
            body.appendChild(note);
            wrap.appendChild(body);
        }

        wrap.appendChild(controls);

        if (opts.dead) {
            degrade();
            swap(placeholder, wrap);
            return;
        }

        /* 预热：先把 CDN 的 TLS 握好、再让浏览器取头部信息，点播放时不用再等 */
        try {
            var origin = new URL(track.url).origin;
            if (!document.querySelector('link[rel=preconnect][href="' + origin + '"]')) {
                var pre = document.createElement('link');
                pre.rel = 'preconnect';
                pre.href = origin;
                pre.crossOrigin = '';
                document.head.appendChild(pre);
            }
        } catch (e) {}

        var audio = document.createElement('audio');
        audio.preload = 'metadata';
        audio.src = track.url;
        wrap.appendChild(audio);

        audio.addEventListener('error', degrade);

        /* 鼠标移到卡片上（或键盘聚焦、手指按下）就开始预取音频，点击时基本秒开 */
        var warmUp = function () {
            if (audio.preload !== 'auto') {
                audio.preload = 'auto';
                audio.load();
            }
        };
        wrap.addEventListener('pointerenter', warmUp);
        wrap.addEventListener('focusin', warmUp);
        play.addEventListener('touchstart', warmUp, { passive: true });

        play.addEventListener('click', function () {
            if (audio.paused) {
                audio.play().catch(function () {});
            } else {
                audio.pause();
            }
        });

        audio.addEventListener('play', function () {
            play.classList.add('is-playing');
            play.setAttribute('aria-label', '暂停');
            play.innerHTML = '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M6 4h3v12H6zM11 4h3v12h-3z"/></svg>';
        });

        audio.addEventListener('pause', function () {
            play.classList.remove('is-playing');
            play.setAttribute('aria-label', '播放');
            play.innerHTML = '<svg viewBox="0 0 20 20" aria-hidden="true"><path d="M6 3.6v12.8L16 10z"/></svg>';
        });

        audio.addEventListener('loadedmetadata', function () {
            dur.textContent = fmt(audio.duration);
        });

        audio.addEventListener('timeupdate', function () {
            if (!audio.duration) { return; }
            var pct = (audio.currentTime / audio.duration) * 100;
            fill.style.width = pct.toFixed(2) + '%';
            bar.setAttribute('aria-valuenow', Math.round(pct));
            cur.textContent = fmt(audio.currentTime);
        });

        audio.addEventListener('ended', function () {
            if (track.trial) {
                degrade();
                return;
            }
            fill.style.width = '0%';
            cur.textContent = '0:00';
        });

        bar.addEventListener('click', function (e) {
            if (!audio.duration) { return; }
            var rect = bar.getBoundingClientRect();
            var ratio = (e.clientX - rect.left) / rect.width;
            audio.currentTime = Math.max(0, Math.min(1, ratio)) * audio.duration;
        });

        swap(placeholder, wrap);
    }

    /* 短代码用 span 占位，可能被 Markdown 包在 <p> 里；figure 不能进 p，所以挪到 p 前面 */
    function swap(placeholder, wrap) {
        var host = placeholder.parentElement;
        if (host && host.tagName === 'P') {
            host.parentNode.insertBefore(wrap, host);
            if (placeholder.parentNode) { placeholder.parentNode.removeChild(placeholder); }

            /* 短代码后面常跟一个 <br>，播放条挪走后会剩一行空行，顺手清掉 */
            while (host.firstChild && (host.firstChild.nodeName === 'BR'
                || (host.firstChild.nodeType === 3 && !host.firstChild.textContent.trim()))) {
                host.removeChild(host.firstChild);
            }

            if (!host.textContent.trim() && !host.querySelector('img,audio,video,iframe')) {
                host.parentNode.removeChild(host);
            }
            return;
        }
        if (placeholder.parentNode) { placeholder.parentNode.replaceChild(wrap, placeholder); }
    }

    /* 先把卡片画出来（歌名来自短代码），接口回来再填内容，避免几秒钟白屏 */
    function skeleton(placeholder, name) {
        var fig = document.createElement('figure');
        fig.className = 'track is-loading';
        fig.innerHTML = '<div class="track-cover is-empty">' + NOTE + '</div>'
            + '<div class="track-meta"><p class="track-title"></p><p class="track-artist">正在获取…</p></div>'
            + '<div class="track-controls"><div class="track-bar"><span class="track-fill"></span></div></div>';
        fig.querySelector('.track-title').textContent = name || '载入中';
        swap(placeholder, fig);

        return fig;
    }

    function upgrade(placeholder) {
        var id = placeholder.getAttribute('data-id');
        if (!id || !window.YISHUN_THEME_URL) { return; }

        var name = placeholder.getAttribute('data-name') || '';
        var link = 'https://music.163.com/#/song?id=' + encodeURIComponent(id);

        /* 可重入：失败后点「重试」会重新画骨架、重新取一遍 */
        function load(target) {
            fetch(window.YISHUN_THEME_URL + 'inc/music.php?id=' + encodeURIComponent(id),
                { referrerPolicy: 'no-referrer' })
                .then(function (r) { return r.json(); })
                .then(function (track) {
                    if (!track || (track.ok !== true && !track.name)) { throw new Error('empty'); }
                    build(track, target, {
                        dead: track.ok !== true,
                        link: link,
                        retry: track.ok === true ? null : function () { load(skeleton(target, name)); }
                    });
                })
                .catch(function () {
                    var note = document.createElement('p');
                    note.className = 'track-error';
                    note.textContent = '这首歌暂时取不到，点这里重试。';
                    note.addEventListener('click', function () { load(skeleton(note, name)); });
                    if (target.parentNode) { target.parentNode.replaceChild(note, target); }
                });
        }

        load(skeleton(placeholder, name));
    }

    function start() {
        [].slice.call(document.querySelectorAll('.aplayer')).forEach(function (placeholder) {
            /* data-local 是主题自己的短代码；data-url 是 Meting 插件原生写法留下的本地文件 */
            var local = placeholder.getAttribute('data-local') || placeholder.getAttribute('data-url');
            if (local) {
                build({
                    name: placeholder.getAttribute('data-name') || '未命名曲目',
                    artist: (placeholder.getAttribute('data-artist') || '').replace(/<[^>]*>/g, ' ').trim(),
                    url: local,
                    cover: placeholder.getAttribute('data-cover') || ''
                }, placeholder, { msg: '音频加载失败，检查一下文件地址' });
                return;
            }
            upgrade(placeholder);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
