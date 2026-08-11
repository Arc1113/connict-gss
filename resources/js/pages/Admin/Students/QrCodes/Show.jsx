import { Head, Link, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '../../../../components/Admin/AdminLayout';
import { ValidationSummary } from '../../../../components/Admin/FormSupport';
import PageHeader from '../../../../components/Admin/PageHeader';
import StatusText from '../../../../components/Admin/StatusText';
import ConfirmDialog from '../../../../components/ConfirmDialog';
import { useState } from 'react';

function formatDateTime(value) {
    if (!value) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function StudentQrCodeShow({
    regenerationHistory,
    student,
    workspace,
}) {
    const { auth, errors } = usePage().props;
    const regeneration = useForm({
        confirmed: false,
        expected_version: student.regeneration_version,
    });
    const [confirmRegeneration, setConfirmRegeneration] = useState(false);

    function regenerateToken(event) {
        event.preventDefault();

        setConfirmRegeneration(true);
    }

    function confirmRegenerateToken() {
        regeneration.post(
            workspace
                ? `${workspace.urls.qr}/${student.id}/regenerate`
                : `/admin/students/${student.id}/qr-code/regenerate`,
            { preserveScroll: true },
        );
    }

    return (
        <AdminLayout>
            <ConfirmDialog busy={regeneration.processing} confirmLabel="Regenerate QR" description={`Every previously printed QR card for ${student.display_name} will stop working immediately. The replacement token is never displayed here.`} onCancel={() => setConfirmRegeneration(false)} onConfirm={confirmRegenerateToken} open={confirmRegeneration} title={`Regenerate QR code for ${student.display_name}?`} tone="warning" />
            <Head title={`QR card — ${student.display_name}`} />
            <PageHeader
                backHref={workspace ? `${workspace.urls.graduates}/${student.id}` : `/admin/students/${student.id}`}
                backLabel="Graduate"
                actions={
                    <>
                        <Link
                            className="admin-button admin-button-secondary"
                            href={workspace?.urls.qr ?? '/admin/students/qr-codes'}
                        >
                            QR preparation
                        </Link>
                        {student.qr.printable && (
                            <a
                                className="admin-button admin-button-primary"
                                href={
                                    workspace
                                        ? `${workspace.urls.qr}/${student.id}/print`
                                        : `/admin/students/${student.id}/qr-code/print`
                                }
                                rel="noreferrer"
                                target="_blank"
                            >
                                Open print sheet
                            </a>
                        )}
                    </>
                }
                description={`${student.ceremony.name} · sequence ${student.sequence_number}`}
                eyebrow="Individual QR preparation"
                title={student.display_name}
            />

            <ValidationSummary errors={errors} />

            <div className="admin-qr-detail-layout">
                <section
                    className="admin-detail-section admin-qr-preview"
                    aria-labelledby="qr-preview-title"
                >
                    <div className="admin-section-heading">
                        <div>
                            <h2 id="qr-preview-title">Print preview</h2>
                            <StatusText
                                label={student.qr.label}
                                value={student.qr.status}
                            />
                        </div>
                    </div>
                    {student.qr.printable ? (
                        <div className="admin-qr-card-preview">
                            <p className="admin-qr-identity">
                                {student.ceremony.name}
                                {student.ceremony.academic_year
                                    ? ` · ${student.ceremony.academic_year}`
                                    : ''}
                            </p>
                            <img
                                alt={`Scannable QR code assigned to ${student.display_name}`}
                                src={student.qr_image_url}
                            />
                            <h3>{student.display_name}</h3>
                            <p>{student.program}</p>
                            <dl>
                                <div>
                                    <dt>Student number</dt>
                                    <dd>{student.student_number}</dd>
                                </div>
                                <div>
                                    <dt>Sequence</dt>
                                    <dd>{student.sequence_number}</dd>
                                </div>
                            </dl>
                        </div>
                    ) : (
                        <div className="admin-qr-blocked" role="status">
                            <h3>Print output is blocked</h3>
                            <ul>
                                {student.qr.print_blockers.map((blocker) => (
                                    <li key={blocker}>{blocker}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <p className="admin-form-note">
                        The encoded token is intentionally hidden. The QR image is
                        protected by the same local account session as this page.
                    </p>
                </section>

                <aside className="admin-detail-section admin-qr-checks">
                    <h2>Before printing</h2>
                    <ol>
                        <li>Verify the graduate identity and ceremony below.</li>
                        <li>Use A4 portrait paper at 100% scale.</li>
                        <li>Disable “Fit to page” and browser headers and footers.</li>
                        <li>Print one test and scan it through the real scanner route.</li>
                    </ol>
                    <dl className="admin-detail-list">
                        <div>
                            <dt>Student number</dt>
                            <dd>{student.student_number}</dd>
                        </div>
                        <div>
                            <dt>Program</dt>
                            <dd>{student.program}</dd>
                        </div>
                        <div>
                            <dt>Eligibility</dt>
                            <dd>{student.eligibility.label}</dd>
                        </div>
                    </dl>
                </aside>
            </div>

            {auth.abilities.regenerate_qr_codes && (
                <section className="admin-danger-zone admin-qr-regeneration">
                    <div>
                        <h2>Replace compromised or lost QR card</h2>
                        <p>
                            Replacement immediately invalidates every older printed
                            card. Do this only after identity verification, then print
                            and test the new card.
                        </p>
                        {!student.qr.regeneratable && (
                            <ul>
                                {student.qr.regeneration_blockers.map((blocker) => (
                                    <li key={blocker}>{blocker}</li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <form onSubmit={regenerateToken}>
                        <label className="admin-qr-confirmation">
                            <input
                                checked={regeneration.data.confirmed}
                                disabled={!student.qr.regeneratable}
                                onChange={(event) =>
                                    regeneration.setData(
                                        'confirmed',
                                        event.target.checked,
                                    )
                                }
                                type="checkbox"
                            />
                            I understand old cards will stop working.
                        </label>
                        <button
                            className="admin-button admin-button-danger"
                            disabled={
                                !student.qr.regeneratable ||
                                !regeneration.data.confirmed ||
                                regeneration.processing
                            }
                            type="submit"
                        >
                            {regeneration.processing
                                ? 'Replacing token…'
                                : 'Replace secure token'}
                        </button>
                    </form>
                </section>
            )}

            {auth.abilities.regenerate_qr_codes && (
                <section className="admin-detail-section">
                    <h2>Replacement history</h2>
                    {regenerationHistory.length === 0 ? (
                        <p className="admin-inline-empty">
                            No token replacement has been recorded.
                        </p>
                    ) : (
                        <div className="admin-table-wrap">
                            <table className="admin-table">
                                <caption className="sr-only">
                                    Previous QR token replacements
                                </caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Replaced</th>
                                        <th scope="col">Authorized by</th>
                                        <th scope="col">Local address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {regenerationHistory.map((entry) => (
                                        <tr key={entry.id}>
                                            <td>{formatDateTime(entry.created_at)}</td>
                                            <th scope="row">{entry.actor}</th>
                                            <td>{entry.ip_address || 'Not recorded'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            )}
        </AdminLayout>
    );
}
