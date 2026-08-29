from pydantic import BaseModel, Field


class HealthResponse(BaseModel):
    status: str = Field(examples=["ok"])
    database: bool
    models_loaded: bool
    embedding_dim: int


class FaceBox(BaseModel):
    x: int
    y: int
    width: int
    height: int
    score: float


class EnrollResponse(BaseModel):
    user_id: int
    embedding_id: int
    samples: int = Field(description="How many enrolments this user now has")
    face: FaceBox


class Candidate(BaseModel):
    user_id: int
    score: float = Field(description="Cosine similarity, 1.0 is identical")


class IdentifyResponse(BaseModel):
    matched: bool = Field(
        description="True when the best candidate cleared the threshold. "
        "This is an identification hint, never an authentication decision."
    )
    user_id: int | None = None
    score: float | None = None
    threshold: float
    face: FaceBox
    candidates: list[Candidate] = Field(
        default_factory=list,
        description="Best few candidates, highest score first, for diagnostics",
    )


class VerifyResponse(BaseModel):
    matched: bool
    user_id: int
    score: float | None = None
    threshold: float
    face: FaceBox


class EnrollmentStatus(BaseModel):
    user_id: int
    enrolled: bool
    samples: int


class DeleteResponse(BaseModel):
    user_id: int
    deleted: int


class ErrorResponse(BaseModel):
    detail: str
    code: str
