from threading import Lock
from typing import Literal

import numpy as np
from fastapi import FastAPI, HTTPException, Response
from kokoro import KPipeline
from pydantic import BaseModel, Field

from audio_processing import encode_mp3, join_sections, normalize_silence

ALLOWED_VOICES = {"am_michael", "am_fenrir", "af_heart", "af_bella"}
MODEL_REVISION = "f3ff3571791e39611d31c381e3a41a3af07b4987"
app = FastAPI(docs_url=None, redoc_url=None, openapi_url=None)
pipeline = KPipeline(lang_code="a", repo_id="hexgrad/Kokoro-82M")
synthesis_lock = Lock()


class Section(BaseModel):
    code: str = Field(max_length=64)
    text: str = Field(min_length=1, max_length=5000)


class SynthesisRequest(BaseModel):
    text: str = Field(min_length=1, max_length=5000)
    sections: list[Section] = Field(default_factory=list, max_length=16)
    voice: str
    speed: float = Field(ge=0.5, le=2.0)
    pause_after_name_ms: int = Field(ge=0, le=1500)
    pause_between_sections_ms: int = Field(ge=0, le=1500)
    delivery_profile: Literal["professional_mc"] = "professional_mc"


@app.get("/health")
def health():
    return {"status": "ready", "model_revision": MODEL_REVISION}


def render_text(text: str, voice: str, speed: float) -> np.ndarray:
    chunks = [audio for _, _, audio in pipeline(text, voice=voice, speed=speed)]
    if not chunks:
        raise ValueError("Kokoro returned no audio")
    return np.concatenate(chunks).astype(np.float32, copy=False)


@app.post("/v1/synthesize")
def synthesize(request: SynthesisRequest):
    if request.voice not in ALLOWED_VOICES:
        raise HTTPException(status_code=422, detail="Unsupported local voice")
    if not synthesis_lock.acquire(blocking=False):
        raise HTTPException(status_code=503, detail="Local synthesis is busy")

    try:
        sections = request.sections or [Section(code="announcement", text=request.text)]
        contextual_text = join_sections(
            [section.text for section in sections],
            request.text,
        )
        audio = normalize_silence(
            render_text(contextual_text, request.voice, request.speed),
        )

        return Response(encode_mp3(audio), media_type="audio/mpeg")
    except (ValueError, OSError) as error:
        raise HTTPException(status_code=500, detail="Local synthesis failed") from error
    finally:
        synthesis_lock.release()
