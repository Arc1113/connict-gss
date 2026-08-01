<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#20262e">
        <meta name="robots" content="noindex, nofollow">
        <title>Connect scanner phone — Graduation Stage System</title>
        <style>
            :root {
                color-scheme: light;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                color: #17191c;
                background: #f4f5f7;
            }

            * { box-sizing: border-box; }

            body {
                min-width: 18rem;
                margin: 0;
                background: #f4f5f7;
            }

            header {
                padding: 1rem;
                color: #fff;
                background: #20262e;
                border-bottom: 3px solid #1769e0;
            }

            header div, main { max-width: 42rem; margin: 0 auto; }
            header p { margin: .2rem 0 0; color: #cbd2dc; font-size: .82rem; }
            header strong { font-size: .95rem; }

            main { padding: 1.25rem 1rem 2.5rem; }
            h1 { margin: 0; font-size: 1.65rem; line-height: 1.15; letter-spacing: -.025em; }
            h2 { margin: 0; font-size: 1rem; }
            p { line-height: 1.5; }

            .notice {
                margin: 1rem 0;
                padding: .75rem .875rem;
                color: #713f00;
                background: #fff3d6;
                border-left: 3px solid #9a5b00;
                font-size: .85rem;
            }

            ol { margin: 1.25rem 0 0; padding: 0; list-style: none; counter-reset: steps; }
            li { position: relative; padding: 0 0 1.25rem 2.75rem; counter-increment: steps; }
            li + li { padding-top: .1rem; border-top: 1px solid #d9dde3; }
            li::before {
                position: absolute;
                top: 0;
                left: 0;
                display: grid;
                width: 1.85rem;
                height: 1.85rem;
                place-items: center;
                color: #fff;
                background: #20262e;
                border-radius: .4rem;
                content: counter(steps);
                font-size: .8rem;
                font-weight: 700;
            }
            li + li::before { top: 1.1rem; }
            li p { margin: .35rem 0 .75rem; color: #5e6670; font-size: .84rem; }

            a.action {
                display: inline-flex;
                min-height: 2.75rem;
                align-items: center;
                justify-content: center;
                padding: .65rem .9rem;
                color: #fff;
                background: #1769e0;
                border-radius: .45rem;
                font-size: .88rem;
                font-weight: 700;
                text-decoration: none;
            }
            a.action:focus-visible { outline: 3px solid #1769e0; outline-offset: 3px; }
            a.action.secondary { color: #17191c; background: #fff; border: 1px solid #bbc2cc; }
            span.unavailable { color: #a22626; font-size: .84rem; font-weight: 700; }

            code {
                display: block;
                margin-top: .55rem;
                padding: .55rem .65rem;
                overflow-wrap: anywhere;
                color: #303740;
                background: #e9ecf0;
                border-radius: .35rem;
                font-family: ui-monospace, "SFMono-Regular", Consolas, monospace;
                font-size: .7rem;
                line-height: 1.45;
            }

            details { margin-top: .75rem; font-size: .82rem; }
            summary { min-height: 2.75rem; padding: .7rem 0; cursor: pointer; font-weight: 700; }
            details p { margin: .25rem 0 .75rem; color: #5e6670; }
            footer { margin-top: 1.5rem; color: #6b737d; font-size: .75rem; }
        </style>
    </head>
    <body>
        <header>
            <div>
                <strong>Graduation Stage System</strong>
                <p>Local scanner phone setup</p>
            </div>
        </header>

        <main>
            <h1>Connect this phone</h1>
            <p>Complete these steps once on the dedicated ceremony network.</p>

            <div class="notice" role="note">
                This temporary setup page is not the secure application. Do not enter an
                operator email or password here.
            </div>

            <ol>
                <li>
                    <h2>Confirm the ceremony network</h2>
                    <p>Keep this phone connected to the same local network as the GSS server.</p>
                </li>
                <li>
                    <h2>Install the local security certificate</h2>
                    <p>
                        Download the certificate, then approve it as a trusted certificate in
                        the phone settings. The operating system requires this confirmation.
                    </p>
                    @if ($connection['certificate_available'])
                        <a class="action secondary" href="{{ $connection['certificate_url'] }}" download>
                            Download GSS certificate
                        </a>
                        <p>Check code shown on the server:</p>
                        <code>{{ $connection['certificate_check_code'] }}</code>
                    @else
                        <span class="unavailable">Certificate not ready. Ask the technical operator to check the server.</span>
                    @endif

                    <details>
                        <summary>iPhone trust instructions</summary>
                        <p>
                            Open the downloaded profile in Settings, install it, then open
                            Settings → General → About → Certificate Trust Settings and enable
                            full trust for the GSS local certificate.
                        </p>
                    </details>
                    <details>
                        <summary>Android trust instructions</summary>
                        <p>
                            Open Settings → Security and privacy → More security settings →
                            Install certificate → CA certificate. Menu wording varies by phone.
                        </p>
                    </details>
                </li>
                <li>
                    <h2>Open the secure scanner</h2>
                    <p>
                        Continue only after the certificate is trusted. The address must begin
                        with HTTPS and must not show a certificate warning.
                    </p>
                    @if ($connection['secure_application'])
                        <a class="action" href="{{ $connection['scanner_url'] }}">
                            Continue to secure scanner
                        </a>
                    @else
                        <span class="unavailable">The server HTTPS address is not configured. Stop and ask the technical operator.</span>
                    @endif
                </li>
                <li>
                    <h2>Finish on the phone</h2>
                    <p>
                        Sign in with the assigned scanner-operator account, install the PWA,
                        choose Camera, and allow camera access.
                    </p>
                </li>
            </ol>

            <footer>
                Camera video stays on this phone. GSS sends only the decoded graduate QR value
                through the authenticated scanner workflow.
            </footer>
        </main>
    </body>
</html>
