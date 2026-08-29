import { Controller } from '@hotwired/stimulus';
import { openCamera, closeCamera, grabFrame, postFrame, drawDetectionBox } from '../js/face-camera';

/**
 * Face-assisted sign in.
 *
 * The camera works out which account you are; the password form that follows
 * does the actual authentication. The controller never signs anyone in: it only
 * fills the username and hands over to the normal login form.
 */
export default class extends Controller {
    static targets = [
        'video', 'overlay', 'placeholder', 'status', 'subtitle',
        'startButton', 'captureButton',
        'cameraStep', 'passwordStep',
        'identifiedName', 'identifiedEmail', 'usernameInput', 'passwordInput',
    ];

    static values = {
        detectUrl: String,
        identifyUrl: String,
        interval: { type: Number, default: 600 },
        // Frames the detector must accept in a row before the button unlocks.
        // A single good frame is easy to hit by accident while moving.
        streak: { type: Number, default: 2 },
    };

    connect() {
        this.stream = null;
        this.timer = null;
        this.inFlight = false;
        this.busy = false;
        this.goodFrames = 0;
    }

    disconnect() {
        this.teardown();
    }

    async start() {
        if (this.stream) {
            return;
        }

        this.setStatus(this.t('starting'));

        try {
            this.stream = await openCamera();
        } catch (error) {
            this.setStatus(this.t('cameraError'), 'error');

            return;
        }

        this.videoTarget.srcObject = this.stream;
        await this.videoTarget.play();

        this.placeholderTarget.hidden = true;
        this.startButtonTarget.hidden = true;
        this.captureButtonTarget.hidden = false;

        this.timer = window.setInterval(() => this.poll(), this.intervalValue);
    }

    async poll() {
        if (this.inFlight || this.busy || !this.stream) {
            return;
        }

        this.inFlight = true;

        try {
            const blob = await grabFrame(this.videoTarget, 320, 0.5);
            const data = await postFrame(this.detectUrlValue, blob, 'gate.jpg');

            this.goodFrames = data.usable ? this.goodFrames + 1 : 0;
            const ready = this.goodFrames >= this.streakValue;

            this.captureButtonTarget.disabled = !ready;
            this.setStatus(data.message ?? '', ready ? 'ok' : null);
            drawDetectionBox(this.overlayTarget, this.videoTarget, data.face, 320);
        } catch (error) {
            this.goodFrames = 0;
            this.captureButtonTarget.disabled = true;
            this.setStatus(this.t('genericError'), 'error');
        } finally {
            this.inFlight = false;
        }
    }

    async capture() {
        if (this.busy || !this.stream) {
            return;
        }

        this.busy = true;
        this.captureButtonTarget.disabled = true;
        this.setStatus(this.t('matching'));

        try {
            const blob = await grabFrame(this.videoTarget, 640, 0.85);
            const data = await postFrame(this.identifyUrlValue, blob, 'capture.jpg');

            if (data.matched && data.user) {
                this.showPasswordStep(data.user);

                return;
            }

            this.setStatus(data.message ?? this.t('genericError'), 'error');
        } catch (error) {
            this.setStatus(this.t('genericError'), 'error');
        } finally {
            this.busy = false;
        }
    }

    /** Camera off, password on. The stream stops as soon as it is not needed. */
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

    restart() {
        this.passwordStepTarget.hidden = true;
        this.cameraStepTarget.hidden = false;
        this.subtitleTarget.hidden = false;

        this.passwordInputTarget.value = '';
        this.usernameInputTarget.value = '';

        this.placeholderTarget.hidden = false;
        this.startButtonTarget.hidden = false;
        this.captureButtonTarget.hidden = true;
        this.captureButtonTarget.disabled = true;

        this.setStatus(this.t('idle'));
    }

    teardown() {
        window.clearInterval(this.timer);
        this.timer = null;

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
