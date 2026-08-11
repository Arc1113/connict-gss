import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import AudioPreview from '../../../components/Admin/AudioPreview';
import AnnouncementWorkspace from '../../../components/Admin/AnnouncementWorkspace';
import { ValidationSummary } from '../../../components/Admin/FormSupport';
import GraduateDetailNav from '../../../components/Admin/GraduateDetailNav';
import StatusText from '../../../components/Admin/StatusText';
import ConfirmDialog from '../../../components/ConfirmDialog';
import { useState } from 'react';

function formatDateTime(value) {
    return value ? new Intl.DateTimeFormat('en-PH', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : 'Not recorded';
}

function detailUrl(student, workspace, section) {
    const base = workspace
        ? `${workspace.urls.graduates}/${student.id}`
        : `/admin/students/${student.id}`;

    return section === 'overview' ? base : `${base}?section=${section}`;
}

function DetailList({ children }) {
    return <dl className="admin-detail-list">{children}</dl>;
}

function Overview({ student, workspace, auth }) {
    const announcementNeedsReview = student.media.audio_status.value === 'needs_review';
    return <>
        <section className="admin-detail-section graduate-readiness">
            <h2>Readiness</h2>
            <DetailList>
                <div><dt>Eligibility</dt><dd><StatusText label={student.eligibility.label} value={student.eligibility.value} /></dd></div>
                <div><dt>Photo</dt><dd><StatusText label={student.media.photo_url ? 'Ready' : 'Missing'} value={student.media.photo_url ? 'approved' : 'missing'} /></dd></div>
                <div><dt>QR</dt><dd><StatusText label={student.qr_token_configured ? 'Ready' : 'Missing'} value={student.qr_token_configured ? 'approved' : 'missing'} /></dd></div>
                <div><dt>Announcement</dt><dd><StatusText label={student.media.audio_status.label} value={student.media.audio_status.value} /></dd></div>
                <div><dt>Stage</dt><dd><StatusText label={student.stage_status.label} value={student.stage_status.value} /></dd></div>
            </DetailList>
        </section>
        {announcementNeedsReview && <section className="admin-detail-section graduate-attention"><h2>Needs attention</h2><p>Announcement audio requires review before stage use.</p><Link href={detailUrl(student, workspace, 'announcement')}>Review announcement</Link></section>}
        <section className="admin-detail-section"><h2>Identity</h2><DetailList>
            <div><dt>Student number</dt><dd>{student.student_number}</dd></div>
            <div><dt>Program</dt><dd>{student.program}</dd></div>
            <div><dt>Major</dt><dd>{student.major || 'Not set'}</dd></div>
        </DetailList></section>
        <section className="admin-detail-section"><h2>Actions</h2><div className="admin-form-actions">
            {auth.abilities.manage_students && <Link className="admin-button admin-button-secondary" href={workspace ? `${workspace.urls.graduates}/${student.id}/edit` : `/admin/students/${student.id}/edit`}>Edit graduate</Link>}
            {auth.abilities.prepare_qr_codes && <Link className="admin-button admin-button-secondary" href={workspace ? `${workspace.urls.qr}/${student.id}` : `/admin/students/${student.id}/qr-code`}>Open QR card</Link>}
            {announcementNeedsReview && <Link className="admin-button admin-button-primary" href={detailUrl(student, workspace, 'announcement')}>Review announcement</Link>}
        </div></section>
    </>;
}

function Academic({ student }) {
    return <section className="admin-detail-section"><h2>Academic</h2><DetailList>
        <div><dt>Student number</dt><dd>{student.student_number}</dd></div><div><dt>First name</dt><dd>{student.first_name}</dd></div><div><dt>Middle name</dt><dd>{student.middle_name || 'Not set'}</dd></div><div><dt>Last name</dt><dd>{student.last_name}</dd></div><div><dt>Suffix</dt><dd>{student.suffix || 'Not set'}</dd></div><div><dt>Display name</dt><dd>{student.display_name}</dd></div><div><dt>Announcement name</dt><dd>{student.announcement_name}</dd></div><div><dt>Pronunciation</dt><dd>{student.pronunciation_guide || 'Not set'}</dd></div><div><dt>College</dt><dd>{student.college || 'Not set'}</dd></div><div><dt>Program</dt><dd>{student.program}</dd></div><div><dt>Major</dt><dd>{student.major || 'Not set'}</dd></div><div><dt>Honors</dt><dd>{student.honors || 'None recorded'}</dd></div><div><dt>Sequence</dt><dd>{student.sequence_number}</dd></div><div><dt>Eligibility</dt><dd>{student.eligibility.label}</dd></div>
    </DetailList><div className="graduate-awards"><h3>Awards</h3>{student.awards.length ? <ul>{student.awards.map((award) => <li key={award.id}>{award.award_name}{!award.is_announcement_enabled && ' (not spoken)'}</li>)}</ul> : <p>No structured awards recorded.</p>}</div></section>;
}

function Media({ student, workspace, auth }) {
    return <div className="admin-student-detail-layout"><section className="admin-detail-section"><h2>Portrait</h2>{student.media.photo_url ? <img alt={`Approved photo for ${student.display_name}`} className="admin-photo-preview admin-photo-preview-large" src={student.media.photo_url} /> : <p className="admin-media-missing">Photo missing. Use Edit graduate to upload an approved portrait.</p>}</section><section className="admin-detail-section"><h2>QR</h2><p>{student.qr_token_configured ? 'QR card is ready for this graduate.' : 'QR preparation is missing.'}</p>{auth.abilities.prepare_qr_codes && <Link className="admin-button admin-button-secondary" href={workspace ? `${workspace.urls.qr}/${student.id}` : `/admin/students/${student.id}/qr-code`}>Open QR card</Link>}</section><section className="admin-detail-section"><h2>Audio-file readiness</h2><StatusText label={student.media.audio_status.label} value={student.media.audio_status.value} /><AudioPreview label={`Announcement audio for ${student.display_name}`} src={student.media.audio_url} /></section></div>;
}

function Announcement({ announcement, student, auth, errors }) {
    const review = useForm({ audio_review_notes: student.media.audio_review_notes ?? '' });
    const [confirmation, setConfirmation] = useState(null);
    function submit(action) { review.post(`/admin/students/${student.id}/audio/${action}`, { preserveScroll: true, onFinish: () => setConfirmation(null) }); }
    return <><ConfirmDialog busy={review.processing} confirmLabel={confirmation === 'approve' ? 'Approve audio' : 'Reject audio'} description={confirmation === 'approve' ? `The active announcement audio for ${student.display_name} will be approved for stage use.` : `The active announcement audio for ${student.display_name} will be rejected. Reviewer notes are retained.`} onCancel={() => setConfirmation(null)} onConfirm={() => submit(confirmation)} open={Boolean(confirmation)} title={confirmation === 'approve' ? `Approve announcement audio for ${student.display_name}?` : `Reject announcement audio for ${student.display_name}?`} tone={confirmation === 'reject' ? 'danger' : 'warning'} /><section className="admin-detail-section"><div className="admin-section-heading"><div><h2>Announcement</h2><p>Review the active audio and the graduate-specific candidate workflow.</p></div><StatusText label={student.media.audio_status.label} value={student.media.audio_status.value} /></div><AudioPreview label={`Announcement audio for ${student.display_name}`} src={student.media.audio_url} /><DetailList><div><dt>Review notes</dt><dd>{student.media.audio_review_notes || 'None recorded'}</dd></div><div><dt>Pronunciation</dt><dd>{student.pronunciation_guide || 'Not set'}</dd></div></DetailList>{(auth.abilities.manage_students || auth.abilities.approve_audio) && <div className="admin-audio-review-form"><label htmlFor="audio_review_notes">Reviewer notes</label><textarea id="audio_review_notes" maxLength="1000" onChange={(event) => review.setData('audio_review_notes', event.target.value)} rows="3" value={review.data.audio_review_notes} />{errors.audio_review_notes && <p className="admin-field-error" role="alert">{errors.audio_review_notes}</p>}<div className="admin-form-actions">{auth.abilities.manage_students && <button className="admin-button admin-button-secondary" disabled={review.processing || !student.media.audio_url} onClick={() => submit('needs-review')} type="button">Mark needs review</button>}{auth.abilities.approve_audio && <><button className="admin-button admin-button-primary" disabled={review.processing || !student.media.audio_url} onClick={() => setConfirmation('approve')} type="button">Approve audio</button><button className="admin-button admin-button-danger" disabled={review.processing || !student.media.audio_url} onClick={() => setConfirmation('reject')} type="button">Reject audio</button></>}</div></div>}</section><AnnouncementWorkspace announcement={announcement} student={student} /></>;
}

function History({ student, auth }) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    return <><ConfirmDialog confirmLabel="Delete graduate" description={`${student.display_name} will be permanently removed from this ceremony. Records with ceremony activity remain protected by the server.`} onCancel={() => setConfirmDelete(false)} onConfirm={() => router.delete(`/admin/students/${student.id}`, { preserveScroll: true })} open={confirmDelete} title={`Delete ${student.display_name}?`} tone="danger" /><section className="admin-detail-section"><h2>Stage history</h2><p>Current stage status: <strong>{student.stage_status.label}</strong></p><h3>Queue history</h3>{student.queue_history.length ? <ul className="graduate-history-list">{student.queue_history.map((item) => <li key={item.id}><strong>{item.status}</strong><span>{formatDateTime(item.occurred_at)}</span></li>)}</ul> : <p>No queue history.</p>}<h3>Scan history</h3>{student.scan_history.length ? <ul className="graduate-history-list">{student.scan_history.map((item) => <li key={item.id}><strong>{item.result}</strong><span>{formatDateTime(item.occurred_at)}</span></li>)}</ul> : <p>No scan history.</p>}</section><section className="admin-detail-section"><h2>Record</h2><DetailList><div><dt>Queue records</dt><dd>{student.queue_items_count}</dd></div><div><dt>Scan logs</dt><dd>{student.scan_logs_count}</dd></div><div><dt>Created</dt><dd>{formatDateTime(student.created_at)}</dd></div><div><dt>Last updated</dt><dd>{formatDateTime(student.updated_at)}</dd></div></DetailList></section>{auth.abilities.delete_students && <section className="admin-danger-zone"><div><h2>Delete unused graduate record</h2><p>This graduate cannot be deleted when ceremony activity references this record.</p></div><button className="admin-button admin-button-danger" onClick={() => setConfirmDelete(true)} type="button">Delete graduate</button></section>}</>;
}

export default function StudentShow({ announcement, section, student, workspace }) {
    const { auth, errors } = usePage().props;
    const heading = `${student.program}${student.sequence_number ? ` · Sequence ${student.sequence_number}` : ''}`;
    return <AdminLayout><Head title={`${student.display_name} · ${section}`} /><div className="graduate-detail-header"><Link className="admin-row-action" href={workspace?.urls.graduates ?? '/admin/students'}>← Graduates</Link><h1>{student.display_name}</h1><p>{heading}</p><small>{student.ceremony.name}</small></div><GraduateDetailNav current={section} student={student} workspace={workspace} /> <ValidationSummary errors={errors} />
        {section === 'overview' && <Overview auth={auth} student={student} workspace={workspace} />}{section === 'academic' && <Academic student={student} />}{section === 'media' && <Media auth={auth} student={student} workspace={workspace} />}{section === 'announcement' && <Announcement announcement={announcement} auth={auth} errors={errors} student={student} />}{section === 'history' && <History auth={auth} student={student} />}
    </AdminLayout>;
}
