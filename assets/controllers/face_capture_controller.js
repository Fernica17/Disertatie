import { Controller } from '@hotwired/stimulus';
import { openCamera, closeCamera, grabFrame, postFrame, drawDetectionBox } from '../js/face-camera';

/**
 * Face search over the person registry.
 *
 * Two ways in, because in practice you either have the person in front of you
 * or only a photo of them:
 *  - the camera, gated by a cheap detection loop so the search button only
 *    lights up once a single face is framed;
 *  - a file, sent straight to the search.
 *
 * Detection runs server side so the preview and the search agree: a frame the
 * button accepts is a frame the search will accept too.
 */
export default class extends Controller {
    static targets = [
        'video', 'overlay', 'placeholder', 'status',
        'startButton', 'stopButton', 'captureButton',
        'cameraMode', 'fileMode', 'cameraPanel', 'filePanel', 'fileInput', 'fileStatus',
        'result', 'resultEmpty', 'resultCard',
        'resultAvatar', 'resultName', 'resultScore', 'resultFields', 'resultLink',
    ];

    static values = {
        detectUrl: String,
        searchUrl: String,
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

    // ---------------------------------------------------------------- //
    // modes
    // ---------------------------------------------------------------- //

    showCamera() {
        this.cameraPanelTarget.hidden = false;
        this.filePanelTarget.hidden = true;
        this.cameraModeTarget.classList.add('is-active');
        this.fileModeTarget.classList.remove('is-active');
    }

    showFile() {
        // Free the camera as soon as it is not on screen.
        this.stop();
        this.cameraPanelTarget.hidden = true;
        this.filePanelTarget.hidden = false;
        this.fileModeTarget.classList.add('is-active');
        this.cameraModeTarget.classList.remove('is-active');
    }

    // ---------------------------------------------------------------- //
    // camera
    // ---------------------------------------------------------------- //

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
        this.stopButtonTarget.hidden = false;

        this.timer = window.setInterval(() => this.poll(), this.intervalValue);
    }

    stop() {
        window.clearInterval(this.timer);
        this.timer = null;

        closeCamera(this.stream);
        this.stream = null;

        if (this.hasVideoTarget) {
            this.videoTarget.srcObject = null;
            drawDetectionBox(this.overlayTarget, this.videoTarget, null, 320);
        }

        if (this.hasPlaceholderTarget) {
            this.placeholderTarget.hidden = false;
            this.startButtonTarget.hidden = false;
            this.stopButtonTarget.hidden = true;
            this.captureButtonTarget.disabled = true;
            this.setStatus(this.t('idle'));
        }
    }

    async poll() {
        if (this.inFlight || this.busy || !this.stream) {
            return;
        }

        this.inFlight = true;

        try {
            const blob = await grabFrame(this.videoTarget, 320, 0.5);
            const data = await postFrame(this.detectUrlValue, blob, 'gate.jpg');

            this.captureButtonTarget.disabled = !data.usable;
            this.setStatus(data.message, data.usable ? 'ok' : null);
            drawDetectionBox(this.overlayTarget, this.videoTarget, data.face, 320);
        } catch (error) {
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
            await this.search(blob, 'capture.jpg', (text, tone) => this.setStatus(text, tone));
        } finally {
            this.busy = false;
        }
    }

    // ---------------------------------------------------------------- //
    // file
    // ---------------------------------------------------------------- //

    async fileChosen() {
        const file = this.fileInputTarget.files?.[0];

        if (!file || this.busy) {
            return;
        }

        this.busy = true;
        this.setFileStatus(this.t('matching'));

        try {
            await this.search(file, file.name, (text, tone) => this.setFileStatus(text, tone));
        } finally {
            this.busy = false;
        }
    }

    // ---------------------------------------------------------------- //
    // search
    // ---------------------------------------------------------------- //

    async search(blob, filename, report) {
        try {
            const data = await postFrame(this.searchUrlValue, blob, filename);

            this.renderResult(data);
            report(data.message ?? '', data.matched ? 'ok' : 'error');
        } catch (error) {
            this.renderResult({ matched: false });
            report(this.t('genericError'), 'error');
        }
    }

    renderResult(data) {
        if (!data.matched || !data.person) {
            this.resultCardTarget.hidden = true;
            this.resultEmptyTarget.hidden = false;

            return;
        }

        const person = data.person;

        this.resultAvatarTarget.innerHTML = person.photoUrl
            ? `<img src="${person.photoUrl}" alt="">`
            : `<span>${this.initials(person.name)}</span>`;

        this.resultNameTarget.textContent = person.name;
        this.resultScoreTarget.textContent = `${this.t('scoreLabel')} ${Number(data.score).toFixed(3)}`;
        this.resultLinkTarget.href = person.detailUrl;

        this.resultFieldsTarget.innerHTML = '';
        this.addField(this.label('nationalId'), person.nationalId);
        this.addField(this.label('idDocument'), person.idDocument);
        this.addField(this.label('birthDate'), this.withAge(person));
        this.addField(this.label('phone'), person.phone);
        this.addField(this.label('email'), person.email);
        this.addField(this.label('address'), person.address);
        this.addField(this.label('notes'), person.notes);

        this.resultEmptyTarget.hidden = true;
        this.resultCardTarget.hidden = false;
    }

    withAge(person) {
        if (!person.birthDate) {
            return null;
        }

        return person.age === null ? person.birthDate : `${person.birthDate} (${person.age})`;
    }

    /** Empty fields are omitted rather than shown as dashes. */
    addField(label, value) {
        if (!value) {
            return;
        }

        const dt = document.createElement('dt');
        dt.textContent = label;

        const dd = document.createElement('dd');
        dd.textContent = value;

        this.resultFieldsTarget.append(dt, dd);
    }

    initials(name) {
        return (name || '?')
            .split(' ')
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0].toUpperCase())
            .join('');
    }

    setStatus(text, tone = null) {
        this.statusTarget.textContent = text;
        this.statusTarget.dataset.tone = tone ?? '';
    }

    setFileStatus(text, tone = null) {
        this.fileStatusTarget.textContent = text;
        this.fileStatusTarget.dataset.tone = tone ?? '';
    }

    label(key) {
        return this.element.dataset[`faceCaptureLabel${key[0].toUpperCase()}${key.slice(1)}`] ?? key;
    }

    t(key) {
        return this.element.dataset[`faceCaptureText${key[0].toUpperCase()}${key.slice(1)}`] ?? '';
    }
}
