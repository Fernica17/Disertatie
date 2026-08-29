# Face service

FastAPI service that associates faces with ERP users and scores a webcam frame
against them. Runs in its own container on the private `erp-network`.

**It does not authenticate anyone.** It answers *"this frame looks like user 42,
similarity 0.71"*. Whether that is enough to let someone in is the ERP's call.

## Model

OpenCV Zoo, downloaded at image build time:

| Role | Model | Size | Licence |
|---|---|---|---|
| Detection | YuNet (`face_detection_yunet_2023mar`) | 233 KB | Apache 2.0 |
| Recognition | SFace (`face_recognition_sface_2021dec`) | 37 MB | Apache 2.0 |

SFace produces 128-d embeddings; they are stored unit-norm, so cosine similarity
is a dot product.

Chosen over InsightFace/ArcFace on purpose: the InsightFace pretrained packs are
licensed for **non-commercial research only**, which does not fit an ERP heading
to production. The accuracy gap (~99.6% vs ~99.8% on LFW) is immaterial at this
scale. Everything model-specific is in `app/recognizer.py` — swapping backends
means rewriting that one module.

## Endpoints

All under `/faces`, all requiring `X-API-Key`. Interactive docs at `/docs`.

| Method | Path | Purpose |
|---|---|---|
| `POST` | `/faces/enroll/{user_id}` | Add a reference face for a user |
| `POST` | `/faces/identify` | 1:N — who is this? |
| `POST` | `/faces/verify/{user_id}` | 1:1 — is this that user? |
| `GET` | `/faces/{user_id}` | Is this user enrolled, how many samples |
| `GET` | `/faces` | User ids that have a face |
| `DELETE` | `/faces/{user_id}` | Erase a user's biometric data |
| `GET` | `/health` | Liveness + database round-trip (no key needed) |

Prefer `verify` over `identify` for login: the ERP states who it expects, so a
false match can only ever be against that one person.

## Storage

Its own PostgreSQL database, `erp_face`, on the shared server — created by
`docker/postgres/init-databases.sh` and migrated by the service at startup.
A separate database rather than a schema inside `erp_db`: Doctrine introspects
the whole ERP database on `schema:validate` and `migrations:diff`, and would
otherwise keep tripping over a `float4[]` column it does not model.

Search is a NumPy scan over all embeddings: sub-millisecond for thousands of
users. Beyond that, switch to pgvector (`embedding::vector`) with an ANN index.

## Licensing

Everything here is free and open source, usable commercially:

| Component | Licence |
|---|---|
| YuNet (detection model) | MIT |
| SFace (recognition model) | Apache 2.0 |
| opencv-python-headless | Apache 2.0 |
| FastAPI / Pydantic | MIT |
| uvicorn / Starlette / NumPy | BSD |
| psycopg 3 | LGPL 3.0 |

psycopg is the only copyleft item. LGPL does not extend to code that merely
uses the library, so it imposes nothing on this project; swap it for `asyncpg`
(Apache 2.0) if your policy forbids LGPL outright.

## Configuration

Environment, prefix `FACE_`:

| Variable | Default | Notes |
|---|---|---|
| `FACE_API_KEY` | `change_me` | Shared secret the ERP sends |
| `FACE_DATABASE_URL` | `postgresql://erp:secretpassword@postgres-service:5432/erp_face` | |
| `FACE_DB_SCHEMA` | `public` | |
| `FACE_MATCH_THRESHOLD` | `0.45` | Cosine. OpenCV's reference for 1:1 is 0.363; this is stricter because identification searches everyone |
| `FACE_DETECTOR_SCORE_THRESHOLD` | `0.6` | Measured YuNet scores for good portraits are 0.72-0.95, so 0.85 rejected real faces |
| `FACE_MAX_IMAGE_BYTES` | `8388608` | |
| `FACE_MAX_IMAGE_EDGE` | `1280` | Frames are downscaled before detection |

## Before production

1. **Liveness.** A printed photo passes today. Add challenge-response (blink,
   turn head) or a passive anti-spoof model such as MiniFASNet.
2. **Second factor.** Face alone is weak; pair it with a password or PIN.
3. **Consent and erasure.** Embeddings are special-category personal data under
   GDPR Art. 9. Record consent, and wire `DELETE /faces/{user_id}` into user
   deletion in the ERP.
4. **Tune the threshold** against your own enrolled population — measure the
   false accept rate rather than trusting the default.

## Local use

```bash
docker compose -f docker-compose.dev.yaml up -d face-service
curl localhost:8092/health
```
