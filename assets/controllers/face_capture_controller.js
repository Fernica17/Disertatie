import { Controller } from '@hotwired/stimulus';
import { openCamera, closeCamera, grabFrame, postFrame, drawDetectionBox } from '../js/face-camera';

const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

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
        'cameraMode', 'fileMode', 'cameraPanel', 'filePanel', 'fileInput', 'fileStatus', 'dropzone',
        'result', 'resultEmpty', 'resultCard',
        'reportBar', 'reportButton', 'reportLink', 'reportStatus',
        'ranking', 'rankingList', 'rankingNote',
        'resultAvatar', 'resultName', 'resultScore', 'resultFields', 'resultLink',
    ];

    static values = {
        detectUrl: String,
        searchUrl: String,
        reportUrl: String,
        interval: { type: Number, default: 600 },
        /* How long a search verdict stays on screen before the detection loop
           may write over it. Without this the "no match" message survives one
           poll tick, which is too short to read. */
        resultHold: { type: Number, default: 5000 },
    };

    connect() {
        this.stream = null;
        this.timer = null;
        this.inFlight = false;
        this.busy = false;
        this.statusHeldUntil = 0;
        this.lastFrame = null;
    }

    disconnect() {
        this.stop();
    }

    // ---------------------------------------------------------------- //
    // modes
    // ---------------------------------------------------------------- //

    showCamera() {
        this.hideReportBar();
        this.cameraPanelTarget.hidden = false;
        this.filePanelTarget.hidden = true;
        this.cameraModeTarget.classList.add('is-active');
        this.fileModeTarget.classList.remove('is-active');
    }

    showFile() {
        // Free the camera as soon as it is not on screen.
        this.stop();
        this.hideReportBar();
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
            if (!this.statusHeld()) {
                this.setStatus(data.message, data.usable ? 'ok' : null);
            }
            drawDetectionBox(this.overlayTarget, this.videoTarget, data.face, 320);
        } catch (error) {
            this.captureButtonTarget.disabled = true;
            if (!this.statusHeld()) {
                this.setStatus(this.t('genericError'), 'error');
            }
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
            await this.search(blob, 'capture.jpg', (text, tone) => this.setStatus(text, tone, true));
        } finally {
            this.busy = false;
        }
    }

    // ---------------------------------------------------------------- //
    // file
    // ---------------------------------------------------------------- //

    /** A click anywhere in the zone opens the picker. */
    pickFile() {
        if (!this.busy) {
            this.fileInputTarget.click();
        }
    }

    async fileChosen() {
        await this.searchFile(this.fileInputTarget.files?.[0]);
    }

    dragOver(event) {
        // Without preventDefault the browser navigates to the dropped file.
        event.preventDefault();
        this.dropzoneTarget.classList.add('is-dragover');
    }

    dragLeave(event) {
        // Moving over a child fires dragleave on the zone; ignore those.
        if (event.relatedTarget && this.dropzoneTarget.contains(event.relatedTarget)) {
            return;
        }

        this.dropzoneTarget.classList.remove('is-dragover');
    }

    async dropped(event) {
        event.preventDefault();
        this.dropzoneTarget.classList.remove('is-dragover');

        await this.searchFile(event.dataTransfer?.files?.[0]);
    }

    /**
     * Rejects the wrong file type here rather than at the server: dragging in a
     * PDF is an easy slip, and the answer should not take a round trip.
     */
    async searchFile(file) {
        if (!file || this.busy) {
            return;
        }

        if (!ACCEPTED_TYPES.includes(file.type)) {
            this.setFileStatus(this.t('fileType'), 'error');

            return;
        }

        this.busy = true;
        this.setFileStatus(this.t('matching'));

        try {
            await this.search(file, file.name, (text, tone) => this.setFileStatus(text, tone));
        } finally {
            this.busy = false;
            // Without this, picking the same file again fires no change event.
            this.fileInputTarget.value = '';
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

            // Held for the report button: it must describe this search, not
            // whatever the camera happens to be looking at when it is pressed.
            this.lastFrame = { blob, filename };
            this.showReportBar();
        } catch (error) {
            this.renderResult({ matched: false });
            report(this.t('genericError'), 'error');
            this.lastFrame = null;
            this.hideReportBar();
        }
    }

    showReportBar() {
        this.reportBarTarget.hidden = false;
        this.reportButtonTarget.hidden = false;
        this.reportButtonTarget.disabled = false;
        this.reportLinkTarget.hidden = true;
        this.setReportStatus('');
    }

    hideReportBar() {
        this.reportBarTarget.hidden = true;
        this.rankingTarget.hidden = true;
    }

    async saveReport() {
        if (!this.lastFrame || this.savingReport) {
            return;
        }

        this.savingReport = true;
        this.reportButtonTarget.disabled = true;
        this.setReportStatus(this.t('reportSaving'));

        try {
            const data = await postFrame(this.reportUrlValue, this.lastFrame.blob, this.lastFrame.filename);

            if (!data.ok) {
                this.setReportStatus(data.message ?? this.t('genericError'), 'error');
                this.reportButtonTarget.disabled = false;

                return;
            }

            this.reportLinkTarget.href = data.reportUrl;
            this.reportLinkTarget.hidden = false;
            this.reportButtonTarget.hidden = true;
            this.setReportStatus(data.message ?? '', 'ok');
        } catch (error) {
            this.setReportStatus(this.t('genericError'), 'error');
            this.reportButtonTarget.disabled = false;
        } finally {
            this.savingReport = false;
        }
    }

    setReportStatus(text, tone = null) {
        this.reportStatusTarget.textContent = text;
        this.reportStatusTarget.dataset.tone = tone ?? '';
    }

    /**
     * The ranked field behind the verdict.
     *
     * Everyone the recogniser ranked, weak ones included and marked as such: a
     * runner-up at 0.34 is what tells you the verdict was not a close call.
     */
    renderRanking(data) {
        const listed = data.candidates ?? [];

        if (listed.length === 0) {
            this.rankingTarget.hidden = true;

            return;
        }

        this.rankingListTarget.innerHTML = '';

        listed.forEach((candidate, index) => {
            const row = document.createElement('li');
            row.className = 'fr-candidate';
            row.classList.toggle('is-below', candidate.below === true);
            row.innerHTML = `
                <span class="fr-rank"></span>
                <span class="fr-avatar"></span>
                <span class="fr-name"></span>
                <span class="fr-flag"></span>
                <span class="fr-score"></span>
                <a class="mg-btn mg-btn-secondary mg-btn-sm" href="#"></a>`;

            row.querySelector('.fr-rank').textContent = index + 1;
            row.querySelector('.fr-name').textContent = candidate.name;

            const flag = row.querySelector('.fr-flag');
            if (candidate.below) {
                flag.textContent = this.t('belowFlag');
            } else {
                flag.remove();
            }

            const score = row.querySelector('.fr-score');
            score.textContent = Number(candidate.score).toFixed(3);
            score.classList.toggle('is-over', candidate.score >= (data.matchThreshold ?? Infinity));

            const avatar = row.querySelector('.fr-avatar');
            if (candidate.photoUrl) {
                const img = document.createElement('img');
                img.src = candidate.photoUrl;
                img.alt = '';
                avatar.appendChild(img);
            } else {
                avatar.textContent = candidate.name.charAt(0).toUpperCase();
            }

            const link = row.querySelector('a');
            link.href = candidate.detailUrl;
            link.textContent = this.t('openRecord');

            this.rankingListTarget.appendChild(row);
        });

        this.rankingNoteTarget.textContent = this.t('thresholdNote')
            .replace('{threshold}', Number(data.reportThreshold ?? 0).toFixed(2));

        this.rankingTarget.hidden = false;
    }

    renderResult(data) {
        this.renderRanking(data);

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

    /** True while a search verdict is still owed its time on screen. */
    statusHeld() {
        return Date.now() < this.statusHeldUntil;
    }

    /**
     * `hold` reserves the status line for a few seconds. Any later plain call
     * releases it, so stopping the camera or starting a new search wins over a
     * verdict that is still counting down.
     */
    setStatus(text, tone = null, hold = false) {
        this.statusTarget.textContent = text;
        this.statusTarget.dataset.tone = tone ?? '';
        this.statusHeldUntil = hold ? Date.now() + this.resultHoldValue : 0;
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
