import { Controller } from '@hotwired/stimulus';
import { openCamera, closeCamera, grabFrame, postFrame, drawDetectionBox } from '../js/face-camera';

/**
 * Hands-free face sign in.
 *
 * The camera starts on its own and the cheap detection loop drives everything:
 * once a face has been framed for a few consecutive polls, one recognition call
 * fires by itself. Recognition is never polled — it costs three times a
 * detection, sends four times the bytes and is rate limited, so running it in a
 * loop would exhaust the allowance within seconds of opening the page.
 *
 * A failed attempt retries a couple of times, then stops and waits for the
 * person rather than hammering the endpoint.
 */
export default class extends Controller {
    static targets = [
        'video', 'overlay', 'placeholder', 'status', 'subtitle',
        'startButton', 'retryButton',
        'cameraStep', 'passwordStep',
        'identifiedName', 'identifiedEmail', 'usernameInput', 'passwordInput',
    ];

    static values = {
        detectUrl: String,
        identifyUrl: String,
        interval: { type: Number, default: 600 },
        // Consecutive detections before recognition fires. A single good frame
        // is easy to hit mid-movement, and a blurred face wastes an attempt.
        streak: { type: Number, default: 3 },
        // Automatic attempts before falling back to a manual retry.
        maxAttempts: { type: Number, default: 3 },
        // Gap between automatic attempts, so a person has time to reposition.
        cooldown: { type: Number, default: 2500 },
        // How long to wait on the permission prompt before offering a way out.
        permissionWait: { type: Number, default: 8000 },
    };

    connect() {
        this.stream = null;
        this.timer = null;
        this.starting = false;
        this.permissionTimer = null;
        this.inFlight = false;
        this.identifying = false;
        this.finished = false;
        this.goodFrames = 0;
        this.attempts = 0;
        this.nextAttemptAt = 0;

        // The page exists to use the camera, so do not make people ask for it.
        // If the browser refuses, the button below takes over.
        this.start();
    }

    disconnect() {
        this.teardown();
    }

    async start() {
        if (this.stream || this.starting) {
            return;
        }

        this.starting = true;
        this.setStatus(this.t('starting'));
        this.startButtonTarget.hidden = true;

        // getUserMedia stays pending while the permission prompt is open, and
        // never settles if it is dismissed. Without this the page would sit on
        // "starting the camera" with no way forward.
        this.permissionTimer = window.setTimeout(() => {
            if (!this.stream) {
                this.setStatus(this.t('permissionWaiting'), 'error');
                this.startButtonTarget.hidden = false;
            }
        }, this.permissionWaitValue);

        try {
            this.stream = await openCamera();
        } catch (error) {
            this.setStatus(this.t('cameraError'), 'error');
            this.startButtonTarget.hidden = false;

            return;
        } finally {
            this.starting = false;
            window.clearTimeout(this.permissionTimer);
        }

        this.videoTarget.srcObject = this.stream;
        await this.videoTarget.play();

        this.placeholderTarget.hidden = true;
        this.startButtonTarget.hidden = true;
        this.retryButtonTarget.hidden = true;

        this.finished = false;
        this.attempts = 0;
        this.timer = window.setInterval(() => this.poll(), this.intervalValue);
    }

    async poll() {
        if (this.inFlight || this.identifying || this.finished || !this.stream) {
            return;
        }

        this.inFlight = true;

        try {
            const blob = await grabFrame(this.videoTarget, 320, 0.5);
            const data = await postFrame(this.detectUrlValue, blob, 'gate.jpg');

            drawDetectionBox(this.overlayTarget, this.videoTarget, data.face, 320);

            if (!data.usable) {
                this.goodFrames = 0;
                this.setStatus(data.message ?? '');

                return;
            }

            this.goodFrames += 1;

            if (this.goodFrames < this.streakValue || Date.now() < this.nextAttemptAt) {
                this.setStatus(data.message ?? '', 'ok');

                return;
            }

            await this.identify();
        } catch (error) {
            this.goodFrames = 0;
            this.setStatus(this.t('genericError'), 'error');
        } finally {
            this.inFlight = false;
        }
    }

    /** One recognition attempt. Only ever called from the detection loop. */
    async identify() {
        this.identifying = true;
        this.attempts += 1;
        this.goodFrames = 0;
        this.setStatus(this.t('matching'));

        try {
            const blob = await grabFrame(this.videoTarget, 640, 0.85);
            const data = await postFrame(this.identifyUrlValue, blob, 'capture.jpg');

            if (data.matched && data.redirect) {
                this.finished = true;
                this.teardown();
                this.setStatus(`${this.t('greeting')} ${data.user.name}`, 'ok');
                window.location.href = data.redirect;

                return;
            }

            if (data.matched && data.user) {
                this.finished = true;
                this.showPasswordStep(data.user);

                return;
            }

            this.handleMiss(data.message);
        } catch (error) {
            this.handleMiss(this.t('genericError'));
        } finally {
            this.identifying = false;
        }
    }

    /** Backs off after a miss, and gives up rather than looping forever. */
    handleMiss(message) {
        if (this.attempts >= this.maxAttemptsValue) {
            this.finished = true;
            this.stopPolling();
            this.setStatus(message ?? this.t('genericError'), 'error');
            this.retryButtonTarget.hidden = false;

            return;
        }

        this.nextAttemptAt = Date.now() + this.cooldownValue;
        this.setStatus(this.t('retrying'), 'error');
    }

    /** Manual restart once the automatic attempts are spent. */
    retry() {
        this.retryButtonTarget.hidden = true;
        this.attempts = 0;
        this.goodFrames = 0;
        this.nextAttemptAt = 0;
        this.finished = false;

        if (!this.timer) {
            this.timer = window.setInterval(() => this.poll(), this.intervalValue);
        }

        this.setStatus(this.t('idle'));
    }

    showPasswordStep(user) {
        this.teardown();

        this.identifiedNameTarget.textContent = `${this.t('greeting')} ${user.name}`;
        this.identifiedEmailTarget.textContent = user.email;
        this.usernameInputTarget.value = user.email;

        this.cameraStepTarget.hidden = true;
        this.passwordStepTarget.hidden = false;
        this.subtitleTarget.hidden = true;

        this.passwordInputTarget.focus();
    }

    stopPolling() {
        window.clearInterval(this.timer);
        this.timer = null;
    }

    teardown() {
        this.stopPolling();
        window.clearTimeout(this.permissionTimer);
        closeCamera(this.stream);
        this.stream = null;
        this.goodFrames = 0;

        if (this.hasVideoTarget) {
            this.videoTarget.srcObject = null;
        }
    }

    setStatus(text, tone = null) {
        this.statusTarget.textContent = text;
        this.statusTarget.dataset.tone = tone ?? '';
    }

    t(key) {
        return this.element.dataset[`faceLoginText${key[0].toUpperCase()}${key.slice(1)}`] ?? '';
    }
}
