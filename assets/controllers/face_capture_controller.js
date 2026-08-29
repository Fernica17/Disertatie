import { Controller } from '@hotwired/stimulus';

/**
 * Camera preview that gates the capture button on a real detection.
 *
 * Two different frames are sent, on purpose:
 *  - the polling loop posts a small, low quality frame to /detect, which only
 *    runs the detector (~11 ms, ~6 KB) and answers "is there exactly one face";
 *  - pressing capture posts a full quality frame to /identify, which computes
 *    the embedding and searches every enrolled face.
 *
 * Detection runs server side rather than in the browser so the preview and the
 * matching use the same model: a frame the button accepts is a frame matching
 * will also accept.
 */
export default class extends Controller {
    static targets = [
        'video', 'overlay', 'placeholder', 'status',
        'startButton', 'stopButton', 'captureButton',
        'result', 'resultEmpty', 'resultCard',
        'resultAvatar', 'resultName', 'resultMeta', 'resultScore',
    ];

    static values = {
        detectUrl: String,
        identifyUrl: String,
        // Slow enough to stay cheap, fast enough to feel responsive.
        interval: { type: Number, default: 600 },
    };

    connect() {
        this.stream = null;
        this.timer = null;
        this.inFlight = false;
        this.busy = false;
    }

    disconnect() {
        this.stop();
    }

    async start() {
        if (this.stream) {
            return;
        }

        this.setStatus(this.t('starting'));

        try {
            this.stream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
                audio: false,
            });
        } catch (error) {
            // Most often a denied permission, or a non-secure origin.
            this.setStatus(this.t('camera_error'), 'error');

            return;
        }

        this.videoTarget.srcObject = this.stream;
        await this.videoTarget.play();

        this.placeholderTarget.hidden = true;
        this.startButtonTarget.hidden = true;
        this.stopButtonTarget.hidden = false;

        this.syncOverlaySize();
        this.timer = window.setInterval(() => this.poll(), this.intervalValue);
    }

    stop() {
        window.clearInterval(this.timer);
        this.timer = null;

        this.stream?.getTracks().forEach((track) => track.stop());
        this.stream = null;

        if (this.hasVideoTarget) {
            this.videoTarget.srcObject = null;
        }

        this.clearOverlay();
        this.setCaptureEnabled(false);

        if (this.hasPlaceholderTarget) {
            this.placeholderTarget.hidden = false;
            this.startButtonTarget.hidden = false;
            this.stopButtonTarget.hidden = true;
            this.setStatus(this.t('idle'));
        }
    }

    /** One detection round trip. Skipped while a previous one is still open. */
    async poll() {
        if (this.inFlight || this.busy || !this.stream) {
            return;
        }

        this.inFlight = true;

        try {
            const blob = await this.grabFrame(320, 0.5);
            const data = await this.post(this.detectUrlValue, blob, 'gate.jpg');

            this.setCaptureEnabled(Boolean(data.usable));
            this.setStatus(data.message, data.usable ? 'ok' : null);
            this.drawBox(data.face);
        } catch (error) {
            this.setCaptureEnabled(false);
            this.setStatus(this.t('detect_error'), 'error');
        } finally {
            this.inFlight = false;
        }
    }

    async capture() {
        if (this.busy || !this.stream) {
            return;
        }

        this.busy = true;
        this.setCaptureEnabled(false);
        this.setStatus(this.t('matching'));

        try {
            const blob = await this.grabFrame(640, 0.85);
            const data = await this.post(this.identifyUrlValue, blob, 'capture.jpg');

            this.renderResult(data);
            this.setStatus(data.message ?? '', data.matched ? 'ok' : 'error');
        } catch (error) {
            this.setStatus(this.t('detect_error'), 'error');
        } finally {
            this.busy = false;
        }
    }

    // ---------------------------------------------------------------- //
    // frames
    // ---------------------------------------------------------------- //

    /** Draws the current video frame to an offscreen canvas and encodes it. */
    grabFrame(width, quality) {
        const video = this.videoTarget;
        const ratio = video.videoHeight / video.videoWidth || 0.75;

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = Math.round(width * ratio);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

        return new Promise((resolve, reject) => {
            canvas.toBlob(
                (blob) => (blob ? resolve(blob) : reject(new Error('canvas encoding failed'))),
                'image/jpeg',
                quality,
            );
        });
    }

    async post(url, blob, filename) {
        const body = new FormData();
        body.append('frame', blob, filename);

        const response = await fetch(url, {
            method: 'POST',
            body,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });

        return response.json();
    }

    // ---------------------------------------------------------------- //
    // rendering
    // ---------------------------------------------------------------- //

    syncOverlaySize() {
        const { clientWidth, clientHeight } = this.videoTarget;
        this.overlayTarget.width = clientWidth;
        this.overlayTarget.height = clientHeight;
    }

    /** Box coordinates come back in the detection frame's space, so rescale. */
    drawBox(face) {
        this.syncOverlaySize();
        const ctx = this.clearOverlay();

        if (!face) {
            return;
        }

        const video = this.videoTarget;
        const scale = video.clientWidth / 320;

        ctx.strokeStyle = '#2ec4b6';
        ctx.lineWidth = 3;
        ctx.strokeRect(face.x * scale, face.y * scale, face.width * scale, face.height * scale);
    }

    clearOverlay() {
        const ctx = this.overlayTarget.getContext('2d');
        ctx.clearRect(0, 0, this.overlayTarget.width, this.overlayTarget.height);

        return ctx;
    }

    renderResult(data) {
        if (!data.matched || !data.user) {
            this.resultCardTarget.hidden = true;
            this.resultEmptyTarget.hidden = false;

            return;
        }

        const user = data.user;

        this.resultAvatarTarget.innerHTML = user.avatarUrl
            ? `<img src="${user.avatarUrl}" alt="">`
            : `<span>${this.initials(user.name)}</span>`;

        this.resultNameTarget.textContent = user.name;
        this.resultMetaTarget.textContent = `${user.email} · ${user.role}`;
        this.resultScoreTarget.textContent = `${this.t('score')}: ${Number(data.score).toFixed(3)}`;

        this.resultEmptyTarget.hidden = true;
        this.resultCardTarget.hidden = false;
    }

    initials(name) {
        return (name || '?')
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0].toUpperCase())
            .join('');
    }

    setCaptureEnabled(enabled) {
        this.captureButtonTarget.disabled = !enabled;
    }

    setStatus(text, tone = null) {
        this.statusTarget.textContent = text;
        this.statusTarget.dataset.tone = tone ?? '';
    }

    /** Strings the controller needs without a round trip, read off the element. */
    t(key) {
        return this.element.dataset[`faceCaptureText${key.replace(/(^|_)(\w)/g, (_, __, c) => c.toUpperCase())}`] ?? '';
    }
}
