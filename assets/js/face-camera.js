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
 * Three coordinate spaces have to line up:
 *  1. the box comes back in the downscaled frame that was sent for detection;
 *  2. the video's own pixels are larger than that frame;
 *  3. `object-fit: cover` then scales the video to fill its element and crops
 *     the overflow, which shifts everything when the element is not the same
 *     aspect ratio as the camera. A square stage over a 4:3 camera crops the
 *     sides, so ignoring that offset draws the box left of the face.
 */
export function drawDetectionBox(canvas, video, face, sourceWidth) {
    const boxWidth = video.clientWidth;
    const boxHeight = video.clientHeight;

    canvas.width = boxWidth;
    canvas.height = boxHeight;

    const ctx = canvas.getContext('2d');
    ctx.clearRect(0, 0, boxWidth, boxHeight);

    if (!face || !video.videoWidth || !video.videoHeight) {
        return;
    }

    const cover = Math.max(boxWidth / video.videoWidth, boxHeight / video.videoHeight);
    const offsetX = (boxWidth - video.videoWidth * cover) / 2;
    const offsetY = (boxHeight - video.videoHeight * cover) / 2;

    // detection frame -> video pixels -> displayed pixels
    const scale = (video.videoWidth / sourceWidth) * cover;

    ctx.strokeStyle = '#2ec4b6';
    ctx.lineWidth = 3;
    ctx.strokeRect(
        offsetX + face.x * scale,
        offsetY + face.y * scale,
        face.width * scale,
        face.height * scale,
    );
}
