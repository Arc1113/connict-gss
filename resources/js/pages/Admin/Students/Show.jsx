import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import AudioPreview from '../../../components/Admin/AudioPreview';
import AnnouncementWorkspace from '../../../components/Admin/AnnouncementWorkspace';
import { ValidationSummary } from '../../../components/Admin/FormSupport';
import PageHeader from '../../../components/Admin/PageHeader';
import StatusText from '../../../components/Admin/StatusText';

function formatDateTime(value) {
    if (!value) {
        return 'Not recorded';
    }

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatFileSize(value) {
    if (!value) {
        return 'Not recorded';
    }

    if (value < 1024 * 1024) {
        return `${Math.round(value / 1024)} KB`;
    }

    return `${(value / (1024 * 1024)).toFixed(1)} MB`;
}

export default function StudentShow({ announcement, student }) {
    const { auth, errors } = usePage().props;
    const audioReview = useForm({
        audio_review_notes: student.media.audio_review_notes ?? '',
    });

    function deleteStudent() {
        if (
            window.confirm(
                `Delete the unused record for ${student.display_name}? This cannot be undone.`,
            )
        ) {
            router.delete(`/admin/students/${student.id}`, {
                preserveScroll: true,
            });
        }
    }

    function submitAudioReview(action, confirmation = null) {
        if (confirmation && !window.confirm(confirmation)) {
            return;
        }

        audioReview.post(`/admin/students/${student.id}/audio/${action}`, {
            preserveScroll: true,
        });
    }

    return (
        <AdminLayout>
            <Head title={student.display_name} />
            <PageHeader
                actions={
                    <>
                        {auth.abilities.generate_announcement_candidates && (
                            <a className="admin-button admin-button-primary" href="#voice-generation">
                                Generate voice
                            </a>
                        )}
                        {auth.abilities.prepare_qr_codes && (
                            <Link
                                className="admin-button admin-button-secondary"
                                href={`/admin/students/${student.id}/qr-code`}
                            >
                                Prepare QR card
                            </Link>
                        )}
                        {auth.abilities.manage_students && (
                            <Link
                                className="admin-button admin-button-primary"
                                href={`/admin/students/${student.id}/edit`}
                            >
                                Edit graduate
                            </Link>
                        )}
                    </>
                }
                description={`${student.ceremony.name} · sequence ${student.sequence_number}`}
                eyebrow="Graduate records"
                title={student.display_name}
            />

            <ValidationSummary errors={errors} />

            <div className="admin-student-detail-layout">
                <section className="admin-detail-section">
                    <div className="admin-section-heading">
                        <h2>Identity and assignment</h2>
                        <StatusText
                            label={student.eligibility.label}
                            value={student.eligibility.value}
                        />
                    </div>
                    <dl className="admin-detail-list">
                        <div>
                            <dt>Student number</dt>
                            <dd>{student.student_number}</dd>
                        </div>
                        <div>
                            <dt>Ceremony</dt>
                            <dd>
                                <Link
                                    href={`/admin/ceremonies/${student.ceremony.id}`}
                                >
                                    {student.ceremony.name}
                                </Link>
                            </dd>
                        </div>
                        <div>
                            <dt>Sequence</dt>
                            <dd>{student.sequence_number}</dd>
                        </div>
                        <div>
                            <dt>QR preparation</dt>
                            <dd>
                                {student.qr_token_configured
                                    ? 'Secure token assigned'
                                    : 'Missing'}
                            </dd>
                        </div>
                        <div>
                            <dt>First name</dt>
                            <dd>{student.first_name}</dd>
                        </div>
                        <div>
                            <dt>Middle name</dt>
                            <dd>{student.middle_name || 'Not set'}</dd>
                        </div>
                        <div>
                            <dt>Last name</dt>
                            <dd>{student.last_name}</dd>
                        </div>
                        <div>
                            <dt>Suffix</dt>
                            <dd>{student.suffix || 'Not set'}</dd>
                        </div>
                        <div>
                            <dt>Stage display name</dt>
                            <dd>{student.display_name}</dd>
                        </div>
                        <div>
                            <dt>Announcement name</dt>
                            <dd>{student.announcement_name}</dd>
                        </div>
                        <div>
                            <dt>Pronunciation guide</dt>
                            <dd>{student.pronunciation_guide || 'Not set'}</dd>
                        </div>
                    </dl>
                </section>

                <aside className="admin-detail-section admin-media-detail">
                    <h2>Presentation media</h2>
                    <section>
                        <h3>Approved photo</h3>
                        {student.media.photo_url ? (
                            <img
                                alt={`Approved photo for ${student.display_name}`}
                                className="admin-photo-preview admin-photo-preview-large"
                                src={student.media.photo_url}
                            />
                        ) : (
                            <p className="admin-media-missing">Photo missing</p>
                        )}
                    </section>
                    <section>
                        <h3>Announcement audio</h3>
                        <StatusText
                            label={student.media.audio_status.label}
                            value={student.media.audio_status.value}
                        />
                        <AudioPreview
                            label={`Announcement audio for ${student.display_name}`}
                            src={student.media.audio_url}
                        />
                        <dl className="admin-media-meta">
                            <div>
                                <dt>Stage policy</dt>
                                <dd>{student.media.audio_stage_label}</dd>
                            </div>
                            <div>
                                <dt>File label</dt>
                                <dd>{student.media.audio_filename || 'Not uploaded'}</dd>
                            </div>
                            <div>
                                <dt>Format</dt>
                                <dd>{student.media.audio_mime_type || 'Not recorded'}</dd>
                            </div>
                            <div>
                                <dt>Size</dt>
                                <dd>{formatFileSize(student.media.audio_file_size)}</dd>
                            </div>
                            <div>
                                <dt>Uploaded</dt>
                                <dd>{formatDateTime(student.media.audio_uploaded_at)}</dd>
                            </div>
                            <div>
                                <dt>Approved</dt>
                                <dd>{formatDateTime(student.media.audio_approved_at)}</dd>
                            </div>
                            <div>
                                <dt>Approved by</dt>
                                <dd>{student.media.audio_approved_by || 'Not recorded'}</dd>
                            </div>
                        </dl>
                    </section>
                </aside>
            </div>

            <section className="admin-detail-section admin-audio-review">
                <div className="admin-section-heading">
                    <div>
                        <h2>Announcement review</h2>
                        <p>
                            Confirm that the spoken clip matches the approved
                            announcement name and pronunciation guide.
                        </p>
                    </div>
                    <StatusText
                        label={student.media.audio_status.label}
                        value={student.media.audio_status.value}
                    />
                </div>
                <dl className="admin-detail-list">
                    <div>
                        <dt>Announcement name</dt>
                        <dd>{student.announcement_name}</dd>
                    </div>
                    <div>
                        <dt>Pronunciation guide</dt>
                        <dd>{student.pronunciation_guide || 'Not set'}</dd>
                    </div>
                    <div>
                        <dt>Review notes</dt>
                        <dd>{student.media.audio_review_notes || 'None recorded'}</dd>
                    </div>
                </dl>

                {(auth.abilities.manage_students || auth.abilities.approve_audio) && (
                    <div className="admin-audio-review-form">
                        <label htmlFor="audio_review_notes">
                            Reviewer notes
                        </label>
                        <textarea
                            aria-invalid={Boolean(errors.audio_review_notes)}
                            id="audio_review_notes"
                            maxLength="1000"
                            onChange={(event) =>
                                audioReview.setData(
                                    'audio_review_notes',
                                    event.target.value,
                                )
                            }
                            rows="3"
                            value={audioReview.data.audio_review_notes}
                        />
                        {errors.audio_review_notes && (
                            <p
                                className="admin-field-error"
                                id="audio_review_notes-error"
                                role="alert"
                            >
                                {errors.audio_review_notes}
                            </p>
                        )}
                        <div className="admin-form-actions">
                            {auth.abilities.manage_students && (
                                <button
                                    className="admin-button admin-button-secondary"
                                    disabled={
                                        audioReview.processing ||
                                        !student.media.audio_url
                                    }
                                    onClick={() =>
                                        submitAudioReview('needs-review')
                                    }
                                    type="button"
                                >
                                    Mark needs review
                                </button>
                            )}
                            {auth.abilities.approve_audio && (
                                <>
                                    <button
                                        className="admin-button admin-button-primary"
                                        disabled={
                                            audioReview.processing ||
                                            !student.media.audio_url ||
                                            student.media.audio_status.value ===
                                                'rejected'
                                        }
                                        onClick={() =>
                                            submitAudioReview(
                                                'approve',
                                                `Approve announcement audio for ${student.display_name}?`,
                                            )
                                        }
                                        type="button"
                                    >
                                        Approve audio
                                    </button>
                                    <button
                                        className="admin-button admin-button-danger"
                                        disabled={
                                            audioReview.processing ||
                                            !student.media.audio_url
                                        }
                                        onClick={() =>
                                            submitAudioReview(
                                                'reject',
                                                `Reject announcement audio for ${student.display_name}? A replacement will be required for stage playback.`,
                                            )
                                        }
                                        type="button"
                                    >
                                        Reject audio
                                    </button>
                                </>
                            )}
                        </div>
                    </div>
                )}
            </section>

            <section className="admin-detail-section">
                <h2>Academic presentation</h2>
                <dl className="admin-detail-list">
                    <div>
                        <dt>College</dt>
                        <dd>{student.college || 'Not set'}</dd>
                    </div>
                    <div>
                        <dt>Program</dt>
                        <dd>{student.program}</dd>
                    </div>
                    <div>
                        <dt>Major</dt>
                        <dd>{student.major || 'Not set'}</dd>
                    </div>
                    <div>
                        <dt>Honors</dt>
                        <dd>{student.honors || 'None recorded'}</dd>
                    </div>
                    <div>
                        <dt>Stage status</dt>
                        <dd>{student.stage_status.label}</dd>
                    </div>
                </dl>
            </section>

            <div id="voice-generation">
                <AnnouncementWorkspace announcement={announcement} student={student} />
            </div>

            <section className="admin-detail-section">
                <h2>Record protection</h2>
                <dl className="admin-detail-list admin-detail-list-compact">
                    <div>
                        <dt>Queue records</dt>
                        <dd>{student.queue_items_count}</dd>
                    </div>
                    <div>
                        <dt>Scan logs</dt>
                        <dd>{student.scan_logs_count}</dd>
                    </div>
                    <div>
                        <dt>Created</dt>
                        <dd>{formatDateTime(student.created_at)}</dd>
                    </div>
                    <div>
                        <dt>Last updated</dt>
                        <dd>{formatDateTime(student.updated_at)}</dd>
                    </div>
                </dl>
            </section>

            {auth.abilities.delete_students && (
                <section className="admin-danger-zone">
                    <div>
                        <h2>Delete unused graduate record</h2>
                        <p>
                            Deletion is blocked after any scan, queue, or presentation
                            history exists.
                        </p>
                    </div>
                    <button
                        className="admin-button admin-button-danger"
                        onClick={deleteStudent}
                        type="button"
                    >
                        Delete graduate
                    </button>
                </section>
            )}
        </AdminLayout>
    );
}
