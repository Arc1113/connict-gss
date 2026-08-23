# Windows Offline TTS Bundle Contract

The Windows client release must include a complete x64 bundle at `runtime-input/tts/`. It is an engineering build input, not something the client installs or downloads.

Minimum contents:

```text
tts/
├── tts-server.exe
├── models/                 # Kokoro model revision f3ff3571791e39611d31c381e3a41a3af07b4987
├── licenses/
│   └── license-inventory.json
├── THIRD_PARTY_NOTICES.txt
└── bundle-manifest.json
```

The executable must listen only on `127.0.0.1:8880`, implement `/health` and `/v1/synthesize`, and never attempt a package installation or network/model download. It must preserve the current request limits and MP3 output behavior in `docker/local-tts/server.py`. MP3 encoding is provided by the pinned `lameenc` Python extension, and English phonemization is provided by the pinned `espeakng-loader` extension, so no separately downloaded Windows executables are required.

`Build-ConnictGssOfflineTts.ps1` creates this bundle on the GitHub Windows runner. It uses pinned Python, Torch, Kokoro, LAME, and phonemizer packages, validates the service with `HF_HUB_OFFLINE=1`, performs MP3 synthesis with both supported voices, writes `bundle-manifest.json`, and generates the dependency/model license inventory.

Before release:

1. Build the bundle on the GitHub Windows x64 runner from the pinned dependency set in `docker/local-tts/requirements.txt`.
2. Run it with outbound network access disabled and verify `/health` reports the pinned model revision.
3. Synthesize both supported voices and validate MP3 output.
4. Review the generated `licenses/license-inventory.json`, copied license files, and `THIRD_PARTY_NOTICES.txt` before production distribution.
5. Register the workflow run, source commit, version, and build attestation in `runtime-provenance.json`.

```powershell
./distribution/windows/scripts/Register-ConnictGssTtsRuntime.ps1 `
  -Version 'kokoro-0.9.4-f3ff3571' `
  -SourceUrl 'https://github.com/OWNER/REPOSITORY/actions/runs/RUN_ID' `
  -LicenseRecord 'generated inventory reviewed for production release' `
  -Verification 'GitHub-Actions-attestation:RUN_ID; source-commit:COMMIT_SHA'
./distribution/windows/scripts/New-ConnictGssRuntimeManifest.ps1
```

Do not use a live Hugging Face download, `pip install`, or an unpinned model revision during client installation.
