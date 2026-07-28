# UI Design System

Status: Phase 3A foundation  
Updated: 2026-07-28

This file defines the shared visual language. It does not authorize new screens
or a large component library.

## Direction

The product is a formal academic event-operations system: calm, restrained,
direct, and readable under pressure. Operator screens use light neutral
surfaces. The stage uses a solid dark field, high-contrast identity, and a
restrained gold accent.

Do not use gradients, glass effects, fake analytics, decorative charts,
card-inside-card layouts, marketing heroes, or external visual assets.

## Color roles

Use semantic roles rather than new one-off colors.
The Phase 3A frontend exposes these roles as `--gss-*` custom properties in
`resources/css/app.css`. Existing screens migrate to them only when touched;
do not create a second token file or parallel naming system.

| Token role | Default | Purpose |
| --- | --- | --- |
| Canvas | `#f2f3f4` | Operator page background |
| Surface | `#ffffff` | Primary working surface |
| Surface muted | `#f7f8f8` | Supporting region |
| Ink | `#14253b` | Primary operator text |
| Ink muted | `#5d6a78` | Supporting text |
| Border | `#cbd2d9` | Dividers and controls |
| Navy | `#0a2036` | Primary action and stage family |
| Gold | `#b4872f` | Restrained institutional accent |
| Success | `#176b52` | Confirmed operation |
| Warning | `#8a5a12` | Attention/recovery |
| Danger | `#9b302b` | Rejected/unsafe operation |
| Focus | `#1c66a5` | Operator focus ring |
| Stage background | `#071a2c` | Audience display |
| Stage text | `#f8fafc` | Audience primary text |
| Stage gold | `#d7b45d` | Audience identity/accent |

Semantic states require text. Never communicate status by color alone.

## Typography

- Operator UI: system sans-serif stack. Do not request a hosted font.
- Stage graduate name: system serif stack for formal emphasis.
- Stage supporting content: system sans-serif.
- Operator body: 16px default, 14px minimum for secondary content.
- Operator headings: compact and proportional to the task, not marketing scale.
- Stage name: fluid and bounded by viewport width and height.
- Use sentence case for interface text. Uppercase is limited to short eyebrows
  and ceremony identity with increased letter spacing.

## Spacing and sizing

Use a 4px base rhythm. Preferred steps: 4, 8, 12, 16, 24, 32, 48, and 64px.

- Compact control height: at least 44px.
- Pressured primary control: at least 48px.
- Working-surface padding: 24–40px on desktop, 20–24px at 1024px.
- Keep related content close and separate sections with space or one divider.

## Borders, radius, and shadow

- Default border: 1px.
- Emphasized status border: 2px only when it aids recognition.
- Control radius: 6–8px.
- Working-surface radius: 10–12px.
- Pills are reserved for compact machine/status tokens, not ordinary labels.
- Prefer no shadow. A single low-contrast surface shadow is the maximum.

## Motion

- Motion must communicate a state change and remain under 160ms.
- Do not delay scanning, presentation, clearing, or recovery.
- Avoid repeated or overlapping animation.
- Under `prefers-reduced-motion: reduce`, remove non-essential transitions and
  smooth scrolling.

## Focus

Operator controls use a 3px visible focus outline with at least 2px offset.
Stage setup interaction must not leave persistent audience-facing controls.

## Local assets

Use only repository or operating-system assets at runtime. Logos, photos, audio,
icons, and fonts must be local and institutionally approved. A missing optional
asset must leave a coherent layout.
