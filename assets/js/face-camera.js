/**
 * Camera helpers shared by the admin recognition page and the face login page.
 *
 * Kept separate from the Stimulus controllers because both need the same
 * capture pipeline (open stream, draw a frame, encode, POST) while rendering
 * completely different things around it.
 */

/**
 * Opens the user-facing camera at roughly 640x480.
 *
 * @throws {Error} when permission is denied or no camera is available
 */
export async function openCamera() {
    return navigator.mediaDevices.getUserMedia({
        video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' },
        audio: false,
    });
}

export function closeCamera(stream) {
    stream?.getTracks().forEach((track) => track.stop());
}

/**
 * Draws the current video frame onto an offscreen canvas and encodes it.
 *
 * Width and quality are deliberately callers' choices: the polling loop wants a
 * small cheap frame, the capture wants a good one.
 *
 * @returns {Promise<Blob>}
 */
export function grabFrame(video, width, quality) {
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

/** POSTs a frame as multipart form data and returns the decoded JSON. */
export async function postFrame(url, blob, filename = 'frame.jpg') {
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

/**
 * Draws a detection box over the video.
 *
 * Boxes come back in the detection frame's coordinate space, so they are
 * rescaled to whatever size the video is actually displayed at.
 */
export function drawDetectionBox(canvas, video, face, sourceWidth) {
    canvas.width = video.clientWidth;
    canvas.height = video.clientHeight;

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, canvas.width, canvas.height);

    if (!face) {
        return;
    }

    const scale = video.clientWidth / sourceWidth;

    ctx.strokeStyle = '#2ec4b6';
    ctx.lineWidth = 3;
    ctx.strokeRect(face.x * scale, face.y * scale, face.width * scale, face.height * scale);
}
