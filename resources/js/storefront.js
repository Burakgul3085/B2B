/**
 * Bayi ana sayfası slaytları.
 *  - Görsel slaytlar 5 saniyede bir değişir; video slaytlarda video bitene kadar (en fazla 30 sn) beklenir.
 *  - Fare üzerindeyken, klavye odağındayken veya sekme gizliyken durur; mobilde parmakla kaydırılır.
 *  - "Hareketi azalt" tercihinde kendiliğinden ilerlemez.
 */
export function heroSlider(count, interval = 5000) {
    return {
        i: 0,
        count,
        paused: false,
        duration: interval,
        timer: null,
        startX: null,
        reduced: window.matchMedia('(prefers-reduced-motion: reduce)').matches,

        init() {
            document.addEventListener('visibilitychange', () => (document.hidden ? this.stop() : this.schedule()));
            this.$nextTick(() => this.activate());
        },

        activate() {
            this.$root.querySelectorAll('video[data-slide]').forEach((video) => {
                if (Number(video.dataset.slide) === this.i) {
                    video.currentTime = 0;
                    video.play().catch(() => {});
                } else {
                    video.pause();
                }
            });
            this.schedule();
        },

        schedule() {
            this.stop();
            if (this.count < 2 || this.paused || this.reduced || document.hidden) {
                return;
            }
            const video = this.$root.querySelector(`video[data-slide="${this.i}"]`);
            // Video uzunluğu biliniyorsa onu bekle; bilinmiyorsa 'ended' olayı ilerletir, 30 sn güvenlik sınırı var.
            this.duration = video ? Math.min((video.duration || 30) * 1000, 30000) : interval;
            this.timer = setTimeout(() => this.next(), this.duration);
        },

        stop() {
            clearTimeout(this.timer);
            this.timer = null;
        },

        go(n) {
            this.i = (n + this.count) % this.count;
            this.activate();
        },
        next() { this.go(this.i + 1); },
        prev() { this.go(this.i - 1); },

        pause() { this.paused = true; this.stop(); },
        resume() { this.paused = false; this.schedule(); },

        videoEnded(n) {
            if (n === this.i && this.count > 1 && !this.paused) {
                this.next();
            }
        },

        get progressStyle() {
            return `animation-duration: ${this.duration}ms; animation-play-state: ${this.paused || this.reduced ? 'paused' : 'running'}`;
        },

        touchStart(event) { this.startX = event.touches[0].clientX; },
        touchEnd(event) {
            if (this.startX === null) {
                return;
            }
            const dx = event.changedTouches[0].clientX - this.startX;
            if (Math.abs(dx) > 45) {
                dx < 0 ? this.next() : this.prev();
            }
            this.startX = null;
        },
    };
}

/**
 * Büyük dosya yükleyen formlar (data-upload-progress): yükleme yüzdesi gösterilir, doğrulama hataları
 * sayfa yenilenmeden formun altında listelenir. Başarılı olunca sunucunun verdiği adrese gidilir.
 */
export function enableUploadProgress() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (event.defaultPrevented || !form.matches('form[data-upload-progress]')) {
            return;
        }
        const hasFile = [...form.querySelectorAll('input[type=file]')].some((input) => input.files.length > 0);
        if (!hasFile) {
            return; // dosya yoksa normal gönderim
        }
        event.preventDefault();

        const bar = form.querySelector('[data-progress-bar]');
        const label = form.querySelector('[data-progress-label]');
        const box = form.querySelector('[data-progress]');
        const errors = form.querySelector('[data-upload-errors]');
        const buttons = form.querySelectorAll('button[type=submit]');
        const show = (text) => { if (errors) { errors.textContent = text; errors.hidden = false; } };

        if (errors) { errors.hidden = true; }
        box?.removeAttribute('hidden');
        buttons.forEach((b) => (b.disabled = true));

        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.upload.addEventListener('progress', (e) => {
            if (!e.lengthComputable) { return; }
            const pct = Math.round((e.loaded / e.total) * 100);
            if (bar) { bar.style.width = pct + '%'; }
            if (label) { label.textContent = pct < 100 ? `Yükleniyor… %${pct}` : 'İşleniyor…'; }
        });
        xhr.addEventListener('load', () => {
            buttons.forEach((b) => (b.disabled = false));
            let body = {};
            try { body = JSON.parse(xhr.responseText || '{}'); } catch { body = {}; }

            if (xhr.status >= 200 && xhr.status < 300 && body.redirect) {
                window.location.href = body.redirect;
                return;
            }
            box?.setAttribute('hidden', '');
            if (xhr.status === 422 && body.errors) {
                show(Object.values(body.errors).flat().join(' '));
            } else if (xhr.status === 413) {
                show('Dosya sunucunun kabul ettiği boyutu aşıyor. Daha küçük bir dosya seçin.');
            } else {
                show('Yükleme tamamlanamadı. Lütfen tekrar deneyin.');
            }
        });
        xhr.addEventListener('error', () => {
            buttons.forEach((b) => (b.disabled = false));
            box?.setAttribute('hidden', '');
            show('Bağlantı kesildi; yükleme tamamlanamadı. Lütfen tekrar deneyin.');
        });
        xhr.send(new FormData(form, event.submitter));
    });
}
