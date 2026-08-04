# Azure Speech Preparation Setup and Security

Status: **ENH-TTS-5 approval and local promotion implemented; Azure remains disabled and unconfigured by default**

Last reviewed against official provider documentation: 2026-08-04

Azure Speech is proposed only for pre-ceremony candidate generation. It is not
a ceremony runtime service. No real key belongs in this repository, React,
Inertia props, logs, audit records, backups, screenshots, or documentation.

ENH-TTS-1 adds the constrained `azure` provider enum and generation metadata.
ENH-TTS-2 adds deterministic SSML and the voice allowlist. ENH-TTS-3 adds blank
environment placeholders, strict configuration validation, the Laravel HTTP
provider, explicit health checking, MP3 validation, private atomic candidate
storage, safe generation records, and usage estimation. Azure remains disabled
in every committed example. The default voice/locale does not prove provider
availability.

ENH-TTS-4 adds an explicit authorized generation button and protected candidate
playback. The browser receives only allowlisted voice labels, configuration
health, safe history, and protected application URLs. It never receives the
subscription key, provider endpoint, request headers, SSML, provider request ID,
or private storage path. Opening a graduate page or refreshing text preview does
not call Azure. ENH-TTS-5 approval also makes no provider request: it validates
and promotes the already-local candidate. Stage hash/readability checks are
local and never synthesize.

## Verified provider baseline

Microsoft's current documentation lists the proposed standard voices
`en-PH-JamesNeural`, `en-PH-RosaNeural`, `fil-PH-AngeloNeural`, and
`fil-PH-BlessicaNeural`. It documents both subscription-key and bearer-token
authorization for Text to Speech REST requests, SSML request bodies with
`application/ssml+xml`, and the `X-Microsoft-OutputFormat` header. The proposed
MP3 output `audio-24khz-48kbitrate-mono-mp3` remains listed.

Provider catalogs, regions, quotas, and pricing can change. ENH-TTS-3 was
reviewed against these official pages on 2026-08-03; deployment must recheck
them:

- <https://learn.microsoft.com/en-us/azure/ai-services/speech-service/language-support>
- <https://learn.microsoft.com/en-us/azure/ai-services/speech-service/rest-text-to-speech>
- <https://learn.microsoft.com/en-us/azure/ai-services/speech-service/speech-synthesis-markup>
- <https://learn.microsoft.com/en-us/azure/ai-services/speech-service/speech-synthesis-markup-voice>
- <https://learn.microsoft.com/en-us/azure/ai-services/speech-service/speech-synthesis-markup-pronunciation>
- <https://azure.microsoft.com/en-us/pricing/details/speech/>

## Environment variables

Credential placeholders remain blank in reviewed example files. The following
disabled defaults are implemented:

```dotenv
AZURE_SPEECH_ENABLED=false
AZURE_SPEECH_KEY=
AZURE_SPEECH_REGION=
AZURE_SPEECH_ENDPOINT=
AZURE_SPEECH_DEFAULT_VOICE=en-PH-JamesNeural
AZURE_SPEECH_DEFAULT_LOCALE=en-PH
AZURE_SPEECH_OUTPUT_FORMAT=audio-24khz-48kbitrate-mono-mp3
AZURE_SPEECH_CONNECT_TIMEOUT=5
AZURE_SPEECH_REQUEST_TIMEOUT=30
AZURE_SPEECH_MAX_RETRIES=3
AZURE_SPEECH_MAX_RESPONSE_BYTES=15728640
AZURE_SPEECH_MONTHLY_CHARACTER_BUDGET=500000
AZURE_SPEECH_BUDGET_WARNING_PERCENT=80
AZURE_SPEECH_BATCH_SIZE=25
AZURE_SPEECH_BATCH_CONCURRENCY=1
```

`AZURE_SPEECH_ENDPOINT` is an optional HTTPS override for the exact Speech
resource endpoint. If blank, a future configuration layer may derive the
documented regional Text to Speech endpoint from `AZURE_SPEECH_REGION`. An
override must be parsed once, require HTTPS, contain no user info/query/fragment,
and match an administrator-approved Azure hostname allowlist. It must never come
from an HTTP request.

No `AZURE_*` value may use a `VITE_` prefix. Configuration should live in a
server-only Laravel config file populated from environment variables; runtime
classes read `config(...)`, not `env(...)` directly. Configuration caching must
remain supported.

## Credential handling

- Create the Speech resource and retrieve its key/endpoint only on an approved
  preparation workstation or server.
- Put the real key only in an ignored `.env` or secret mechanism approved for
  the local deployment. Keep example values blank.
- Send credentials only in the server-to-Azure authorization header.
- Never persist keys/tokens in PostgreSQL or files, serialize configuration to
  Inertia, expose it from health endpoints, or interpolate it into exception
  messages.
- Never log request headers, the complete HTTP request/response, cURL commands,
  Guzzle debug output, configuration dumps, or Laravel HTTP client traces.
- Never store a bearer token. If bearer authentication is later selected, cache
  it server-side only for less than its provider lifetime and redact it exactly
  like the subscription key.
- Rotate a suspected key in Azure, replace the ignored local value, clear
  Laravel configuration cache safely, and verify logs/backups before resuming
  preparation.

The initial recommendation is the subscription-key header because it is the
smallest server-only REST integration. The institution must approve it or select
Microsoft Entra/bearer authentication before ENH-TTS-3.

## Region and endpoint configuration

The configured key, resource endpoint, and region must describe the same Azure
Speech resource. Fail configuration validation before any generation when:

- Azure is disabled;
- a required key, region, or resolved endpoint is missing;
- the endpoint is not approved HTTPS;
- the voice/locale/output format is outside the application allowlist; or
- timeout, retry, response-size, budget, or concurrency settings are outside
  bounded application limits.

Do not accept an endpoint, region, voice, locale, or output format directly from
untrusted browser input. Browser selections map to server allowlisted values.

## Voice and locale allowlist

Implemented server-side comparison set in `config/announcements.php`:

| Locale | Voice | Proposed use |
| --- | --- | --- |
| `en-PH` | `en-PH-JamesNeural` | Initial male English (Philippines) candidate |
| `en-PH` | `en-PH-RosaNeural` | Initial female English (Philippines) candidate |
| `fil-PH` | `fil-PH-AngeloNeural` | Filipino male comparison |
| `fil-PH` | `fil-PH-BlessicaNeural` | Filipino female comparison |

`en-PH-JamesNeural` with locale `en-PH` is the approved initial template-domain
default; that configuration value is not a provider availability claim. Before
using any voice for a ceremony, a later provider phase must verify availability
for the configured resource/region and compare it with fictional and
institution-approved test names. Never assume voice quality implies correct
pronunciation. HD/custom/personal voices, styles, roles, multi-voice
announcements, and arbitrary catalog selection are outside the initial scope.

## Request and output contract

Proposed synthesis request:

```text
POST {approved Text to Speech endpoint}
Content-Type: application/ssml+xml
X-Microsoft-OutputFormat: audio-24khz-48kbitrate-mono-mp3
User-Agent: bounded non-secret application identifier
Authorization: server-only approved header
Body: deterministic server-built escaped SSML
```

The initial output is one 24 kHz, 48 kbit/s, mono MP3. It is compact and
compatible with the existing managed upload/stage-browser path. Adding another
production format requires a verified operational need and browser/venue test.

The ENH-TTS-2 SSML builder selects one allowlisted voice/locale, accepts only an
integer speaking rate from -20% through +10%, and accepts only integer pauses
from 0 through 1500 ms. PHP `XMLWriter` creates the namespace, elements, and
attributes; every dynamic spoken value is written with `XMLWriter::text` and is
therefore XML-escaped. Known raw SSML in source fields is blocking. The result
retains the exact human-readable plain text and controlled break metadata. A
human pronunciation note is not automatically IPA or a valid phoneme value.

The request contract is executable only from the authorized backend generation
action when `AZURE_SPEECH_ENABLED=true` and all server configuration validates.
There is no browser-provided endpoint, key, region, output format, SSML, or raw
header value.

Configuration-only health is the default and makes no request. An explicit
network health call fetches the Azure voice-list endpoint with bounded timeouts
and returns only `available`, `authentication_failed`, `rate_limited`,
`unavailable`, or `unknown`. It does not synthesize sample audio.

## Timeouts, retries, and rate limits

Initial bounds:

- connection timeout: 5 seconds;
- whole request timeout: 30 seconds;
- maximum 3 retries after the first attempt;
- exponential backoff with jitter, honoring a safe bounded `Retry-After` when
  provided;
- sequential single-student requests and default batch concurrency 1; and
- maximum accepted response size 15 MiB, reviewed before implementation against
  expected announcement length.

Retry only failures likely to be transient: network connection failure, timeout,
HTTP 429, 502, or 503. Microsoft notes that 502 can also indicate invalid
headers, so a safely classified repeated request/header defect must stop rather
than consume every retry. Do not retry invalid configuration, HTTP
400/401/403/415, unsupported voice, invalid SSML, unsafe endpoint, local
validation failure, or candidate-storage failure. Each attempt increments the
generation's bounded attempt count. An exhausted rate limit pauses the
item/batch with safe recovery guidance; it must not spin, fan out, or fall back
to live generation during the ceremony.

## Response validation and atomic storage

Before accepting success, require HTTP 200, an expected audio content type,
non-empty content, bounded size, and an MP3 signature/structure check suitable
for the approved implementation. Stream to a private temporary file on the same
filesystem, validate it, then atomically rename it to the collision-resistant
candidate path. Delete partial files on every failure.

Do not trust a provider filename, redirect to an unapproved host, accept HTML or
JSON as audio, or store response headers/body in logs. Candidate validation does
not replace human listening review.

## Character usage and budget

Before each request, calculate and show the provider-billable character estimate
from the exact SSML/plain-text policy selected in ENH-TTS-3. Persist the
generation character count and aggregate successful generated characters by
calendar month, provider, and result. Show:

- proposed request characters;
- successful estimated month-to-date characters;
- configured local monthly budget and warning threshold; and
- estimated remaining amount, labeled as an estimate.

As of this review, Azure's public pricing page lists 0.5 million neural TTS
characters free per month for the Free F0 tier. This is a warning reference, not
a guarantee or entitlement. Pricing, character definitions, quotas, region,
resource tier, and Azure's meter control billing. The configurable local counter
must never be presented as an invoice or authoritative quota balance.

Do not call Azure on page load, on every keystroke, or automatically after a
source edit. Reuse an identical successful candidate when policy allows;
otherwise require explicit force-regeneration confirmation. Batch selection and
concurrency remain bounded.

ENH-TTS-7 implements the concurrency-one boundary with a dedicated database
queue named `tts-preparation`. One job handles one graduate and dispatches the
next only after PostgreSQL progress is committed. Laravel jobs use one attempt;
the provider retains the bounded retry behavior above. Rate limits, timeouts,
service unavailability, or worker interruption pause persisted work for an
explicit administrator resume. The worker is preparation-only and is excluded
from ceremony-critical health.

## Preparation-only health check

A future authorized health action may perform two levels:

1. **Configuration check (default):** no network call; report only
   `disabled`, `misconfigured`, or `configured` without echoing values.
2. **Explicit provider check (preparation mode only):** a bounded authenticated
   request approved during implementation, returning only `available`,
   `authentication_failed`, `rate_limited`, `unavailable`, or `unknown`.

It must not synthesize billable sample text unless the administrator explicitly
chooses voice comparison and sees the character estimate. Azure failure is a
warning in preparation mode and is ignored by ceremony-critical readiness.

## Safe error classification

Only allowlisted codes and bounded redacted guidance may be stored or shown:

| Safe code | Typical classification | Retry |
| --- | --- | --- |
| `azure_not_configured` | Disabled/missing/invalid server config | No |
| `azure_request_invalid` | Invalid local typed synthesis request | No |
| `azure_authentication_failed` | 401/403 | No |
| `azure_rate_limited` | 429 | Bounded |
| `azure_invalid_ssml` | 400 attributable to validated request | No |
| `azure_timeout` | Connection/request timeout | Bounded |
| `azure_service_unavailable` | 502/503 or temporary connection failure | Bounded |
| `candidate_validation_failed` | Empty/wrong/oversized/invalid audio | No |
| `candidate_storage_failed` | Private atomic write failure | No |
| `azure_response_unexpected` | Other sanitized provider response | No by default |

Never persist provider response bodies, authorization headers, URLs containing
parameters, stack traces, private candidate paths, graduate source text in a
failure message, or arbitrary upstream error text. Server logs may include only
generation ID, safe code, attempt number, HTTP status where safe, elapsed time,
and exception class.

## Development, testing, preparation, and ceremony modes

| Mode | Azure | Network | Permitted behavior |
| --- | --- | --- | --- |
| Unit/feature testing | Disabled/faked | None | Fake provider responses; verify timeouts, retry mapping, redaction, hashes, and storage compensation |
| Local development | Disabled by default | Optional | Compose/preview with fake provider; controlled fictional integration only after explicit approval |
| Preparation | Explicitly enabled | Required for generation | Authorized individual/low-concurrency batch generation and review |
| Ceremony | Disabled or unreachable | Not required | Existing approved local files only; no TTS worker or provider health dependency |

Provider fakes must not use a real key. Any controlled integration uses fictional
data, a non-production ceremony, private storage, explicit cost awareness, and a
post-run secret/output inspection.

## Controlled integration procedure

After ENH-TTS-3 implementation is approved, an administrator and technical
operator will:

1. Confirm institutional approval, budget owner, region, authentication method,
   data-processing/privacy requirements, and voice allowlist.
2. Create or select an Azure Speech resource in the approved tenant/subscription
   and region.
3. Record the resource region and endpoint without credentials in the technical
   deployment record.
4. Place the real key only in the ignored preparation-server environment.
5. Keep Azure disabled in committed examples and ceremony deployment by default.
6. Clear/rebuild Laravel configuration cache without displaying environment
   values.
7. Run the non-network configuration check.
8. Invoke the explicit network health method from a controlled backend
   preparation session; confirm its safe status without printing configuration.
9. Invoke `GenerateAnnouncementCandidate` for a non-production ceremony record
   containing only fictional content, then verify the resulting hidden path is
   under `storage/app/private/announcement-candidates` with mode-0600 content.
10. Inspect the fictional candidate, character estimate, generation row, retry
    count, and safe failure behavior. Remove it according to the approved test
    retention procedure.
11. Verify no key/token/private path appears in HTML, Inertia props, JavaScript
    bundles, HTTP responses, logs, audit records, backups, screenshots, or Git.
12. Disable Azure and disconnect internet, then run the complete ceremony
    playback/recovery rehearsal.

## Decisions requiring human approval

- Azure tenant/subscription, resource tier, region, and budget owner.
- Subscription-key or Microsoft Entra/bearer authentication.
- Whether endpoint override is needed and its exact hostname allowlist.
- Initial default voice/locale after pronunciation comparison.
- Speaking-rate and pause bounds.
- Whether data encoders may initiate provider requests.
- Character-count definition, monthly budget, warning percent, batch size, and
  force-regeneration authority.
- Candidate maximum size and retention period.
- Whether an explicit network health check is allowed and what request it uses.
- Preparation server connectivity window and the procedure that disables Azure
  before ceremony mode.
