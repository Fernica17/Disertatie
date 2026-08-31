"""Enrolment and matching endpoints.

None of these authenticate anybody. `identify` answers "this frame looks like
user 42, similarity 0.71"; whether that is good enough to let someone in is a
decision for the ERP, which knows about passwords, second factors and lockouts.
"""

from __future__ import annotations

import logging

import numpy as np
from fastapi import APIRouter, Depends, File, HTTPException, Path, Query, Request, UploadFile, status

from ..config import Settings, get_settings
from ..recognizer import (
    FaceError,
    MultipleFacesDetected,
    NoFaceDetected,
    cosine_similarity,
)
from ..schemas import (
    Candidate,
    DeleteResponse,
    DetectResponse,
    EnrollmentStatus,
    EnrollResponse,
    FaceBox,
    IdentifyResponse,
    VerifyResponse,
)
from ..security import require_api_key

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/faces", tags=["faces"], dependencies=[Depends(require_api_key)])

MODEL_NAME = "sface_2021dec"
MAX_CANDIDATES = 5
MAX_CANDIDATES_LIMIT = 25

# Which set of faces a call operates on. Login accounts and the person registry
# are stored separately so an id from one can never be answered for the other.
CollectionParam = Query(
    default="users",
    pattern="^[a-z][a-z0-9_]{0,31}$",
    description="Face collection to work within, e.g. users or persons",
)


async def _read_upload(image: UploadFile, settings: Settings) -> bytes:
    payload = await image.read()

    if not payload:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, "Uploaded file is empty")

    if len(payload) > settings.max_image_bytes:
        raise HTTPException(
            status.HTTP_413_REQUEST_ENTITY_TOO_LARGE,
            f"Image exceeds {settings.max_image_bytes} bytes",
        )

    return payload


def _embed_or_400(request: Request, payload: bytes):
    """Runs detection + embedding, mapping face problems onto 4xx responses."""
    recognizer = request.app.state.recognizer

    try:
        image = recognizer.decode(payload)
        return recognizer.embed(image)
    except NoFaceDetected:
        raise HTTPException(status.HTTP_422_UNPROCESSABLE_ENTITY, "No face detected in the image")
    except MultipleFacesDetected as exc:
        raise HTTPException(
            status.HTTP_422_UNPROCESSABLE_ENTITY,
            f"Found {exc.count} faces; the frame must contain exactly one",
        )
    except FaceError as exc:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, str(exc))


def _to_box(face) -> FaceBox:
    x, y, w, h = face.box
    return FaceBox(x=x, y=y, width=w, height=h, score=round(face.score, 4))


@router.post(
    "/detect",
    response_model=DetectResponse,
    summary="Is there exactly one usable face in the frame?",
)
async def detect(
    request: Request,
    image: UploadFile = File(...),
    settings: Settings = Depends(get_settings),
) -> DetectResponse:
    """Runs detection without computing an embedding.

    Meant to be polled by a camera preview so the capture button only becomes
    available once a single face is framed. Using the same detector as the
    recognition endpoints means the preview never green-lights a frame that
    enrolment or matching would then reject.
    """
    payload = await _read_upload(image, settings)
    recognizer = request.app.state.recognizer

    try:
        frame = recognizer.decode(payload)
    except FaceError as exc:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, str(exc))

    faces = recognizer.detect(frame)

    return DetectResponse(
        faces=len(faces),
        usable=len(faces) == 1,
        face=_to_box(faces[0]) if len(faces) == 1 else None,
    )


@router.post(
    "/enroll/{user_id}",
    response_model=EnrollResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Associate a face with a user",
)
async def enroll(
    request: Request,
    user_id: int = Path(ge=1),
    image: UploadFile = File(...),
    collection: str = CollectionParam,
    settings: Settings = Depends(get_settings),
) -> EnrollResponse:
    """Adds one more reference face for a user.

    Enrol several frames per person — different lighting, with and without
    glasses. Matching keeps the best score across a user's samples, so extra
    samples raise the recall without loosening the threshold.
    """
    payload = await _read_upload(image, settings)
    embedding, face = _embed_or_400(request, payload)

    store = request.app.state.store
    embedding_id = store.add(user_id, embedding, MODEL_NAME, collection)

    logger.info("Enrolled a face for %s/%s (embedding %s)", collection, user_id, embedding_id)

    return EnrollResponse(
        user_id=user_id,
        embedding_id=embedding_id,
        samples=store.count_for(user_id, collection),
        face=_to_box(face),
    )


@router.post(
    "/identify",
    response_model=IdentifyResponse,
    summary="Find out who is in the frame (1:N)",
)
async def identify(
    request: Request,
    image: UploadFile = File(...),
    collection: str = CollectionParam,
    candidates: int = Query(
        default=MAX_CANDIDATES,
        ge=1,
        le=MAX_CANDIDATES_LIMIT,
        description="How many ranked candidates to return alongside the verdict",
    ),
    settings: Settings = Depends(get_settings),
) -> IdentifyResponse:
    """Scores the frame against every enrolled face and returns the best match.

    The response is a hint for the ERP, not a login.
    """
    payload = await _read_upload(image, settings)
    embedding, face = _embed_or_400(request, payload)

    store = request.app.state.store
    known = store.all_faces(collection)

    if not known:
        return IdentifyResponse(
            matched=False,
            threshold=settings.match_threshold,
            face=_to_box(face),
        )

    # Keep the best score per user: several samples of the same person must not
    # crowd the candidate list.
    best_per_user: dict[int, float] = {}
    for stored in known:
        score = cosine_similarity(embedding, stored.embedding)
        if score > best_per_user.get(stored.subject_id, -1.0):
            best_per_user[stored.subject_id] = score

    ranked = sorted(best_per_user.items(), key=lambda item: item[1], reverse=True)
    top_user, top_score = ranked[0]
    matched = top_score >= settings.match_threshold

    return IdentifyResponse(
        matched=matched,
        user_id=top_user if matched else None,
        score=round(top_score, 4),
        threshold=settings.match_threshold,
        face=_to_box(face),
        candidates=[
            Candidate(user_id=uid, score=round(score, 4))
            for uid, score in ranked[:candidates]
        ],
    )


@router.post(
    "/verify/{user_id}",
    response_model=VerifyResponse,
    summary="Check the frame against one specific user (1:1)",
)
async def verify(
    request: Request,
    user_id: int = Path(ge=1),
    image: UploadFile = File(...),
    collection: str = CollectionParam,
    settings: Settings = Depends(get_settings),
) -> VerifyResponse:
    """Safer than `identify` for login: the ERP says who it expects, so a
    false match can only ever be against that one person."""
    payload = await _read_upload(image, settings)
    embedding, face = _embed_or_400(request, payload)

    store = request.app.state.store
    known = store.faces_for(user_id, collection)

    if not known:
        raise HTTPException(
            status.HTTP_404_NOT_FOUND,
            f"User {user_id} has no enrolled face",
        )

    best = max(cosine_similarity(embedding, stored.embedding) for stored in known)

    return VerifyResponse(
        matched=best >= settings.match_threshold,
        user_id=user_id,
        score=round(float(best), 4),
        threshold=settings.match_threshold,
        face=_to_box(face),
    )


@router.get(
    "/{user_id}",
    response_model=EnrollmentStatus,
    summary="Whether a user has an enrolled face",
)
async def enrollment_status(
    request: Request,
    user_id: int = Path(ge=1),
    collection: str = CollectionParam,
) -> EnrollmentStatus:
    samples = request.app.state.store.count_for(user_id, collection)

    return EnrollmentStatus(user_id=user_id, enrolled=samples > 0, samples=samples)


@router.get("", response_model=list[int], summary="User ids that have an enrolled face")
async def enrolled_users(request: Request, collection: str = CollectionParam) -> list[int]:
    return request.app.state.store.enrolled_ids(collection)


@router.delete(
    "/{user_id}",
    response_model=DeleteResponse,
    summary="Erase every face enrolled for a user",
)
async def delete_faces(
    request: Request,
    user_id: int = Path(ge=1),
    collection: str = CollectionParam,
) -> DeleteResponse:
    """Biometric data is special-category personal data; erasure has to be a
    first-class operation, not a manual DELETE."""
    deleted = request.app.state.store.delete_for(user_id, collection)
    logger.info("Deleted %s embedding(s) for %s/%s", deleted, collection, user_id)

    return DeleteResponse(user_id=user_id, deleted=deleted)
