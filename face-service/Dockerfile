FROM python:3.12-slim

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    PIP_NO_CACHE_DIR=1

# opencv-python-headless still needs libGL's loader and glib at runtime
RUN apt-get update && apt-get install -y --no-install-recommends \
        libglib2.0-0 \
        libgl1 \
        curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /srv/face

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

# Baked into the image so the container never needs the network at runtime
COPY scripts/ ./scripts/
RUN python scripts/download_models.py

COPY app/ ./app/

# Inference is CPU-bound; leaving it single-worker keeps the models loaded once.
# Scale by adding replicas rather than workers.
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000"]
