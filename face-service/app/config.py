from functools import lru_cache
from pathlib import Path

from pydantic_settings import BaseSettings, SettingsConfigDict

BASE_DIR = Path(__file__).resolve().parent.parent


class Settings(BaseSettings):
    """Runtime configuration, read from the environment."""

    model_config = SettingsConfigDict(env_prefix="FACE_", env_file=".env", extra="ignore")

    # --- service ---
    api_key: str = "change_me"
    debug: bool = False

    # --- database ---
    # The service owns a whole database of its own, so Doctrine never introspects
    # it and the two schemas can evolve independently.
    database_url: str = "postgresql://erp:secretpassword@postgres-service:5432/erp_face"
    db_schema: str = "public"

    # --- models ---
    detector_path: Path = BASE_DIR / "models" / "face_detection_yunet_2023mar.onnx"
    recognizer_path: Path = BASE_DIR / "models" / "face_recognition_sface_2021dec.onnx"

    # --- thresholds ---
    # OpenCV's reference cosine threshold for SFace is 0.363 for *verification*.
    # Identification searches every enrolled face, so the chance of a false match
    # grows with the population: this default is deliberately stricter.
    match_threshold: float = 0.45
    # Detection confidence only answers "is there a face here" - the security
    # decision is the recognition threshold above. Measured YuNet scores for good
    # frontal portraits sit around 0.75-0.95, so 0.85 rejected real faces; 0.6
    # still filters noise while accepting webcam-quality frames.
    detector_score_threshold: float = 0.6
    max_image_bytes: int = 8 * 1024 * 1024
    # Longest edge an uploaded frame is resized to before detection.
    max_image_edge: int = 1280


@lru_cache
def get_settings() -> Settings:
    return Settings()
