FROM python:3.12-slim-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    HF_HOME=/opt/huggingface \
    HF_HUB_DISABLE_TELEMETRY=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1

RUN apt-get update \
    && apt-get install -y --no-install-recommends espeak-ng lame libsndfile1 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /srv/local-tts
COPY docker/local-tts/requirements.txt ./requirements.txt
RUN pip install --no-cache-dir --index-url https://download.pytorch.org/whl/cpu torch==2.6.0 \
    && pip install --no-cache-dir -r requirements.txt

COPY docker/local-tts/download_model.py ./download_model.py
RUN python download_model.py
RUN mkdir -p /opt/huggingface/hub/models--hexgrad--Kokoro-82M/refs \
    && printf '%s' 'f3ff3571791e39611d31c381e3a41a3af07b4987' \
        > /opt/huggingface/hub/models--hexgrad--Kokoro-82M/refs/main
RUN pip install --no-cache-dir \
    https://github.com/explosion/spacy-models/releases/download/en_core_web_sm-3.8.0/en_core_web_sm-3.8.0-py3-none-any.whl

ENV HF_HUB_OFFLINE=1 \
    TRANSFORMERS_OFFLINE=1 \
    TRANSFORMERS_CACHE=/tmp/transformers

COPY docker/local-tts/server.py ./server.py
RUN useradd --create-home --uid 10001 localtts \
    && chown -R localtts:localtts /srv/local-tts /opt/huggingface
USER localtts

EXPOSE 8880
CMD ["python", "-m", "uvicorn", "server:app", "--host", "0.0.0.0", "--port", "8880", "--workers", "1", "--limit-concurrency", "8"]
