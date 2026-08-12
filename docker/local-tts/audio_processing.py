import numpy as np


SAMPLE_RATE = 24000
QUIET_THRESHOLD_DBFS = -45.0
EDGE_PADDING_MS = 100
LONG_SILENCE_MS = 500
TARGET_SILENCE_MS = 250


def join_sections(section_texts: list[str], fallback_text: str) -> str:
    parts = [text.strip() for text in section_texts if text.strip()]
    return " ".join(parts) if parts else fallback_text.strip()


def normalize_silence(audio: np.ndarray) -> np.ndarray:
    samples = np.asarray(audio, dtype=np.float32).reshape(-1)
    if samples.size == 0:
        return samples

    threshold = 10 ** (QUIET_THRESHOLD_DBFS / 20)
    voiced = np.flatnonzero(np.abs(samples) > threshold)
    if voiced.size == 0:
        return samples

    edge_padding = milliseconds_to_samples(EDGE_PADDING_MS)
    start = max(0, int(voiced[0]) - edge_padding)
    end = min(samples.size, int(voiced[-1]) + edge_padding + 1)
    trimmed = samples[start:end]

    quiet = np.abs(trimmed) <= threshold
    transitions = np.diff(np.concatenate(([False], quiet, [False])).astype(np.int8))
    quiet_starts = np.flatnonzero(transitions == 1)
    quiet_ends = np.flatnonzero(transitions == -1)
    minimum_run = milliseconds_to_samples(LONG_SILENCE_MS)
    target_run = milliseconds_to_samples(TARGET_SILENCE_MS)
    output: list[np.ndarray] = []
    cursor = 0

    for quiet_start, quiet_end in zip(quiet_starts, quiet_ends, strict=True):
        if quiet_start == 0 or quiet_end == trimmed.size:
            continue
        if quiet_end - quiet_start < minimum_run:
            continue

        output.append(trimmed[cursor:quiet_start])
        midpoint = quiet_start + ((quiet_end - quiet_start) // 2)
        replacement_start = max(quiet_start, midpoint - (target_run // 2))
        replacement_end = min(quiet_end, replacement_start + target_run)
        replacement_start = max(quiet_start, replacement_end - target_run)
        output.append(trimmed[replacement_start:replacement_end])
        cursor = quiet_end

    if cursor == 0:
        return trimmed.astype(np.float32, copy=False)

    output.append(trimmed[cursor:])
    return np.concatenate(output).astype(np.float32, copy=False)


def milliseconds_to_samples(milliseconds: int) -> int:
    return int(SAMPLE_RATE * milliseconds / 1000)
