from __future__ import annotations

import logging
from contextlib import asynccontextmanager

from fastapi import FastAPI

from .config import get_settings
from .db import FaceStore
from .recognizer import FaceRecognizer
from .routers import faces, health

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)-8s [%(name)s] %(message)s",
)

logger = logging.getLogger(__name__)


@asynccontextmanager
async def lifespan(app: FastAPI):
    settings = get_settings()

    if settings.api_key == "change_me":
        logger.warning("FACE_API_KEY is still the default; set a real value")

    # Both are loaded up front so a broken model or database surfaces at boot
    # rather than on the first login attempt.
    store = FaceStore(settings)
    store.connect()
    app.state.store = store

    app.state.recognizer = FaceRecognizer(settings)

    logger.info("Face service ready (match threshold %.3f)", settings.match_threshold)

    yield

    store.close()
    logger.info("Face service stopped")


def create_app() -> FastAPI:
    settings = get_settings()

    app = FastAPI(
        title="ERP Face Service",
        version="0.1.0",
        summary="Face enrolment and matching for the ERP",
        description=(
            "Returns similarity scores for faces. It never authenticates anyone: "
            "the ERP decides what a score is worth, alongside passwords and any "
            "second factor. Intended to be reachable only on the private network."
        ),
        lifespan=lifespan,
        docs_url="/docs",
        openapi_url="/openapi.json",
        debug=settings.debug,
    )

    app.include_router(health.router)
    app.include_router(faces.router)

    return app


app = create_app()
