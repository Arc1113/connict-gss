# Windows Offline TTS Bundle Contract

The Windows client release must include a complete x64 bundle at `runtime-input/tts/`. It is an engineering build input, not something the client installs or downloads.

Minimum contents:

```text
tts/
├── tts-server.exe
├── models/                 # Kokoro model revision f3ff3571791e39611d31c381e3a41a3af07b4987
├── tools/                  # bundled LAME and eSpeak-NG runtime
├── licenses/
├── THIRD_PARTY_NOTICES.txt
└── bundle-manifest.json
```

The executable must listen only on `127.0.0.1:8880`, implement `/health` and `/v1/synthesize`, and never attempt a package installation or network/model download. It must preserve the current request limits and MP3 output behavior in `docker/local-tts/server.py`.

`Build-ConnictGssOfflineTts.ps1` creates this bundle on the Windows release runner. It uses the pinned Python/Torch/Kokoro versions, validates the service with `HF_HUB_OFFLINE=1`, performs MP3 synthesis with both supported voices, and writes `bundle-manifest.json`. Supply checksum-verified Windows x64 LAME and eSpeak-NG inputs plus a reviewed license directory (including `THIRD_PARTY_NOTICES.txt`).

Before release:

1. Build the bundle on Windows x64 from the pinned Python/Torch/Kokoro dependency set used by `docker/local-tts/requirements.txt`.
2. Run it with outbound network access disabled and verify `/health` reports the pinned model revision.
3. Synthesize both supported voices and validate MP3 output.
4. Review licenses for Python, PyTorch, Kokoro, model assets, eSpeak, LAME, and any bundled DLLs.
5. Stage it below `distribution/windows/runtime-input/tts/`.
6. Register its immutable build source, version, license record, and SHA-256/attestation:

```powershell
./distribution/windows/scripts/Register-ConnictGssTtsRuntime.ps1 `
  -Version '1.0.0' `
  -SourceUrl 'https://your-internal-release-registry/connict-gss-tts-1.0.0.zip' `
  -LicenseRecord 'licenses reviewed in release record GSS-TTS-1.0.0' `
  -Verification 'archive-sha256:...; release-attestation:...'
./distribution/windows/scripts/New-ConnictGssRuntimeManifest.ps1
```

Do not use a live Hugging Face download, `pip install`, or an unpinned model revision during client installation.
