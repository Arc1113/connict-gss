import { Head } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import PageHeader from '../../../components/Admin/PageHeader';
import StatusText from '../../../components/Admin/StatusText';

function CopyButton({ label, value }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1800);
        } catch {
            setCopied(false);
        }
    };

    return (
        <button className="secondary-action" onClick={copy} type="button">
            {copied ? 'Copied' : label}
        </button>
    );
}

export default function DeviceConnectionsIndex({ connection, realtime }) {
    return (
        <AdminLayout>
            <Head title="Connect devices" />
            <PageHeader
                actions={
                    <button
                        className="secondary-action"
                        onClick={() => window.print()}
                        type="button"
                    >
                        Print setup sheet
                    </button>
                }
                description="Prepare an approved phone as a camera scanner without typing a server address or opening a terminal."
                eyebrow="System"
                title="Connect scanner phone"
            />

            <section
                aria-labelledby="device-status-heading"
                className="device-connection-status"
            >
                <div>
                    <h2 id="device-status-heading">Enrollment readiness</h2>
                    <p>
                        The QR contains only the local setup address. It does not contain
                        an account, password, or graduate QR token.
                    </p>
                </div>
                <StatusText
                    label={
                        connection.enrollment_ready
                            ? 'Ready for enrollment'
                            : connection.secure_application
                              ? 'Certificate unavailable'
                              : 'HTTPS deployment required'
                    }
                    value={connection.enrollment_ready ? 'active' : 'warning'}
                />
            </section>

            <div className="device-connection-workspace">
                <section
                    aria-labelledby="phone-qr-heading"
                    className="device-connection-qr"
                >
                    <div className="admin-section-heading">
                        <div>
                            <h2 id="phone-qr-heading">Scan from the phone</h2>
                            <p>Use the phone camera while connected to the ceremony LAN.</p>
                        </div>
                        <span className="device-connection-step">Step 1</span>
                    </div>

                    {connection.enrollment_ready ? (
                        <>
                            <div className="device-connection-qr-frame">
                                <img
                                    alt="QR code that opens the local GSS phone setup page"
                                    src={connection.onboarding_qr_data_uri}
                                />
                            </div>

                            <code>{connection.onboarding_url}</code>
                            <CopyButton label="Copy setup address" value={connection.onboarding_url} />
                        </>
                    ) : (
                        <div className="device-connection-blocked" role="alert">
                            <strong>Phone enrollment is not ready</strong>
                            <p>
                                {connection.secure_application
                                    ? 'Start the deployment again so GSS can publish the public local certificate.'
                                    : 'Configure APP_URL with the server HTTPS LAN address, then start the production deployment.'}
                            </p>
                        </div>
                    )}
                </section>

                <section
                    aria-labelledby="phone-steps-heading"
                    className="device-connection-instructions"
                >
                    <div className="admin-section-heading">
                        <div>
                            <h2 id="phone-steps-heading">Complete on the phone</h2>
                            <p>The phone will show the platform-specific certificate steps.</p>
                        </div>
                        <span className="device-connection-step">Steps 2–5</span>
                    </div>

                    <ol>
                        <li>
                            <strong>Install and trust the GSS certificate</strong>
                            <span>Approve the operating-system security prompts.</span>
                        </li>
                        <li>
                            <strong>Continue to the secure scanner</strong>
                            <span>Confirm the HTTPS page opens without a warning.</span>
                        </li>
                        <li>
                            <strong>Sign in as the assigned operator</strong>
                            <span>Use a named scanner-operator account, never a shared admin account.</span>
                        </li>
                        <li>
                            <strong>Install and test the PWA</strong>
                            <span>Allow camera access and rehearse an accepted and duplicate QR.</span>
                        </li>
                    </ol>

                    {connection.secure_application && (
                        <div className="device-connection-address">
                            <div>
                                <span>Secure scanner address</span>
                                <code>{connection.scanner_url}</code>
                            </div>
                            <CopyButton label="Copy scanner address" value={connection.scanner_url} />
                        </div>
                    )}
                </section>
            </div>

            <section
                aria-labelledby="certificate-check-heading"
                className="device-certificate-check"
            >
                <div className="admin-section-heading">
                    <div>
                        <h2 id="certificate-check-heading">Certificate identity</h2>
                        <p>
                            Compare this check code before approving a certificate obtained
                            from the ceremony LAN.
                        </p>
                    </div>
                    <strong>{connection.certificate_check_code ?? 'Not available'}</strong>
                </div>
                {connection.certificate_fingerprint && (
                    <details>
                        <summary>Show full SHA-256 fingerprint</summary>
                        <code>{connection.certificate_fingerprint}</code>
                    </details>
                )}
            </section>

            <section
                aria-labelledby="realtime-diagnostics-heading"
                className="device-connection-recovery"
            >
                <div className="admin-section-heading">
                    <div>
                        <h2 id="realtime-diagnostics-heading">Live update diagnostics</h2>
                        <p>{realtime.guidance}</p>
                    </div>
                    <StatusText
                        label={realtime.configured && realtime.service_reachable ? 'Ready' : 'Review required'}
                        value={realtime.configured && realtime.service_reachable ? 'active' : 'warning'}
                    />
                </div>
                <dl>
                    <div>
                        <dt>Browser endpoint</dt>
                        <dd><code>{realtime.endpoint}</code></dd>
                    </div>
                    <div>
                        <dt>Endpoint host</dt>
                        <dd>{realtime.host_source}</dd>
                    </div>
                    <div>
                        <dt>Page origin</dt>
                        <dd>{realtime.origin_allowed ? `${realtime.origin} is allowed` : `${realtime.origin} is not allowed`}</dd>
                    </div>
                    <div>
                        <dt>Reverb service</dt>
                        <dd>{realtime.service_reachable ? 'Reachable from Laravel' : 'Not reachable from Laravel'}</dd>
                    </div>
                </dl>
            </section>

            <section
                aria-labelledby="device-recovery-heading"
                className="device-connection-recovery"
            >
                <h2 id="device-recovery-heading">If the phone cannot connect</h2>
                <dl>
                    <div>
                        <dt>Page does not open</dt>
                        <dd>Confirm the phone is on the ceremony LAN, then scan the setup QR again.</dd>
                    </div>
                    <div>
                        <dt>HTTPS warning remains</dt>
                        <dd>Enable full trust for the installed certificate, then fully close and reopen the browser.</dd>
                    </div>
                    <div>
                        <dt>Camera is blocked</dt>
                        <dd>Allow Camera in the browser site settings, return to Scanner, and choose Start camera.</dd>
                    </div>
                    <div>
                        <dt>Address changed</dt>
                        <dd>Reserve the server address in the ceremony router and repeat phone enrollment.</dd>
                    </div>
                </dl>
            </section>
        </AdminLayout>
    );
}
