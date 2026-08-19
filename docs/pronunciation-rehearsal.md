# Pronunciation rehearsal gate

Complete this check before producing a client `.exe` release.

1. Open **Pronunciation dictionary** and test every enabled built-in rule with each voice that will be offered for the ceremony.
2. Test Azure and local Kokoro separately; do not copy a replacement between providers unless both previews are acceptable.
3. Include complete Latin-honor phrases, academic abbreviations, representative Filipino and international names, programs, majors, and awards.
4. Generate private candidates for representative graduates and confirm display spelling remains unchanged while speech uses the reviewed replacement.
5. Change one rule and verify only affected generated candidates become outdated. Confirm uploaded human recordings remain unchanged.
6. Approve regenerated candidates only after listening through the venue mixer, amplifier, and speakers at operating volume.
7. Record the tested application commit, Kokoro engine revision, Azure voices, venue equipment, reviewer, and rehearsal date in the release evidence.

If any enabled built-in replacement fails, disable it or replace it with a provider/voice-specific variant before packaging. A successful text transformation test alone is not sufficient for release.

Latin-honor respellings are intentionally not bundled before this calibration. Add the complete phrases as provider- or voice-specific rules only after the selected voices have passed this rehearsal; do not promote an assumed spelling to a common built-in.
