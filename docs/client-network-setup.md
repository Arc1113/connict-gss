# Connict GSS Ceremony Network Setup

Use a dedicated local router or switch. The server laptop needs a private LAN IPv4 address. Connict GSS detects it during first-run setup and displays the HTTPS address for other devices.

- Connect the server laptop, scanner, stage, and stage-control devices to the same private network.
- On each operator device, open the HTTPS address displayed by Connict GSS. Use the Device Connections page for the role-specific links and phone certificate onboarding.
- If the server address changes, restart Connict GSS and use its network reconfiguration action before reconnecting devices.
- Do not expose PostgreSQL, TTS, Reverb, or the internal application runtime to the LAN. Only the HTTPS endpoint is public to ceremony devices.

Internet access is not required during the ceremony.
