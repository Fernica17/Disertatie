"""Face detection and embedding.

Wraps OpenCV's YuNet detector and SFace recognizer. Everything model-specific
lives here: swapping in a different backend (InsightFace, for example) means
reimplementing this module and nothing else, as long as `embed` keeps returning
a unit-norm float32 vector and `EMBEDDING_DIM` is updated to match.
"""

from __future__ import annotations

import logging
import threading
from dataclasses import dataclass

import cv2
import numpy as np

from .config import Settings

logger = logging.getLogger(__name__)

EMBEDDING_DIM = 128


class FaceError(Exception):
    """Raised when a frame cannot yield exactly one usable face."""


class NoFaceDetected(FaceError):
    pass


class MultipleFacesDetected(FaceError):
    def __init__(self, count: int) -> None:
        super().__init__(f"expected exactly one face, found {count}")
        self.count = count


@dataclass(frozen=True)
class DetectedFace:
    """A detected face plus the geometry SFace needs for alignment."""

    box: tuple[int, int, int, int]
    score: float
    raw: np.ndarray


class FaceRecognizer:
    """Thread-safe wrapper over the two ONNX graphs.

    The OpenCV model objects are stateful — `setInputSize` mutates the detector —
    so all access is serialised. Inference is short (tens of milliseconds), and
    uvicorn runs endpoints in a threadpool, so a single lock is enough.
    """

    def __init__(self, settings: Settings) -> None:
        for path in (settings.detector_path, settings.recognizer_path):
            if not path.exists():
                raise FileNotFoundError(
                    f"Model not found: {path}. Run scripts/download_models.py first."
                )

        self._settings = settings
        self._lock = threading.Lock()

        self._detector = cv2.FaceDetectorYN.create(
            model=str(settings.detector_path),
            config="",
            input_size=(320, 320),
            score_threshold=settings.detector_score_threshold,
            nms_threshold=0.3,
            top_k=5000,
        )
        self._recognizer = cv2.FaceRecognizerSF.create(
            model=str(settings.recognizer_path),
            config="",
        )

        logger.info("Loaded detector %s", settings.detector_path.name)
        logger.info("Loaded recognizer %s", settings.recognizer_path.name)

    # ------------------------------------------------------------------ #
    # decoding
    # ------------------------------------------------------------------ #

    def decode(self, payload: bytes) -> np.ndarray:
        """Decodes image bytes into a BGR array, downscaling very large frames."""
        buffer = np.frombuffer(payload, dtype=np.uint8)
        image = cv2.imdecode(buffer, cv2.IMREAD_COLOR)

        if image is None:
            raise FaceError("payload is not a decodable image")

        longest = max(image.shape[:2])
        limit = self._settings.max_image_edge

        if longest > limit:
            scale = limit / longest
            image = cv2.resize(
                image,
                (round(image.shape[1] * scale), round(image.shape[0] * scale)),
                interpolation=cv2.INTER_AREA,
            )

        return image

    # ------------------------------------------------------------------ #
    # detection + embedding
    # ------------------------------------------------------------------ #

    def detect(self, image: np.ndarray) -> list[DetectedFace]:
        height, width = image.shape[:2]

        with self._lock:
            self._detector.setInputSize((width, height))
            _, raw = self._detector.detect(image)

        if raw is None:
            return []

        faces = []
        for row in raw:
            x, y, w, h = (int(round(v)) for v in row[:4])
            faces.append(DetectedFace(box=(x, y, w, h), score=float(row[-1]), raw=row))

        return faces

    def embed(self, image: np.ndarray) -> tuple[np.ndarray, DetectedFace]:
        """Returns a unit-norm embedding for the single face in `image`."""
        faces = self.detect(image)

        if not faces:
            raise NoFaceDetected("no face detected in the frame")

        if len(faces) > 1:
            raise MultipleFacesDetected(len(faces))

        face = faces[0]

        with self._lock:
            aligned = self._recognizer.alignCrop(image, face.raw)
            feature = self._recognizer.feature(aligned)

        vector = np.asarray(feature, dtype=np.float32).flatten()
        norm = float(np.linalg.norm(vector))

        if norm == 0.0:
            raise FaceError("recognizer returned a zero vector")

        # Storing unit-norm vectors turns cosine similarity into a plain dot
        # product, which keeps the search in db.py trivial.
        return vector / norm, face


def cosine_similarity(a: np.ndarray, b: np.ndarray) -> float:
    """Cosine similarity for vectors that are already unit-norm."""
    return float(np.dot(a, b))
