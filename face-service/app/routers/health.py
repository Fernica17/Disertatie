from fastapi import APIRouter, Request

from ..recognizer import EMBEDDING_DIM
from ..schemas import HealthResponse

router = APIRouter(tags=["health"])


@router.get("/health", response_model=HealthResponse)
async def health(request: Request) -> HealthResponse:
    """Liveness plus a real database round-trip, for the container healthcheck."""
    store = request.app.state.store
    recognizer = getattr(request.app.state, "recognizer", None)

    database_ok = store.ping()

    return HealthResponse(
        status="ok" if database_ok and recognizer is not None else "degraded",
        database=database_ok,
        models_loaded=recognizer is not None,
        embedding_dim=EMBEDDING_DIM,
    )
