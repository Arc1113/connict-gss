from hashlib import sha256
from pathlib import Path

from huggingface_hub import snapshot_download

REPOSITORY = "hexgrad/Kokoro-82M"
REVISION = "f3ff3571791e39611d31c381e3a41a3af07b4987"
EXPECTED = {
    "kokoro-v1_0.pth": "496dba118d1a58f5f3db2efc88dbdc216e0483fc89fe6e47ee1f2c53f18ad1e4",
    "voices/af_heart.pt": "0ab5709b8ffab19bfd849cd11d98f75b60af7733253ad0d67b12382a102cb4ff",
    "voices/af_bella.pt": "8cb64e02fcc8de0327a8e13817e49c76c945ecf0052ceac97d3081480e8e48d6",
}

snapshot = Path(snapshot_download(
    repo_id=REPOSITORY,
    revision=REVISION,
    allow_patterns=["config.json", *EXPECTED.keys()],
))

for relative_path, expected_hash in EXPECTED.items():
    actual_hash = sha256((snapshot / relative_path).read_bytes()).hexdigest()
    if actual_hash != expected_hash:
        raise RuntimeError(f"Checksum mismatch for {relative_path}: {actual_hash}")

Path("/opt/kokoro-model-revision").write_text(f"{REVISION}\n", encoding="utf-8")
