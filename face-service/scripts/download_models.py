"""Fetches the OpenCV Zoo ONNX models the recognizer needs.

Run at image build time so the container never reaches the network at runtime.
Both models are permissively licensed (see face-service/README.md).
"""

from __future__ import annotations

import hashlib
import sys
import urllib.request
from pathlib import Path

MODELS_DIR = Path(__file__).resolve().parent.parent / "models"

BASE = "https://github.com/opencv/opencv_zoo/raw/main/models"

MODELS: dict[str, str] = {
    # YuNet detector, ~227 KB
    "face_detection_yunet_2023mar.onnx": f"{BASE}/face_detection_yunet/face_detection_yunet_2023mar.onnx",
    # SFace recognizer, 128-d embeddings, ~37 MB
    "face_recognition_sface_2021dec.onnx": f"{BASE}/face_recognition_sface/face_recognition_sface_2021dec.onnx",
}


def download(name: str, url: str) -> None:
    target = MODELS_DIR / name

    if target.exists() and target.stat().st_size > 0:
        print(f"  {name}: already present ({target.stat().st_size} bytes)")
        return

    print(f"  {name}: downloading from {url}")
    request = urllib.request.Request(url, headers={"User-Agent": "erp-face-service"})

    with urllib.request.urlopen(request, timeout=120) as response:
        payload = response.read()

    if len(payload) < 1024:
        raise RuntimeError(f"{name}: refusing a {len(payload)}-byte response, the URL is probably wrong")

    target.write_bytes(payload)
    digest = hashlib.sha256(payload).hexdigest()[:16]
    print(f"  {name}: {len(payload)} bytes, sha256:{digest}")


def main() -> int:
    MODELS_DIR.mkdir(parents=True, exist_ok=True)
    print(f"Downloading models into {MODELS_DIR}")

    for name, url in MODELS.items():
        download(name, url)

    return 0


if __name__ == "__main__":
    sys.exit(main())
