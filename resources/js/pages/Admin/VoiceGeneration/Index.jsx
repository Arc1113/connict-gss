import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import PageHeader from '../../../components/Admin/PageHeader';

export default function VoiceGeneration({ workspace }) {
    const { ceremony, counts, permissions, providers, review_queue: reviewQueue } = workspace;
    const settingsUrl = workspace.urls?.settings ?? '/admin/ceremonies';
    const batchUrl = workspace.urls?.batches ?? '/admin/ceremonies';

    return (
        <AdminLayout>
            <Head title="Announcements" />
            <PageHeader
                eyebrow={ceremony ? 'Ceremony workspace' : 'Preparation workspace'}
                title="Announcements"
            />

            {!ceremony && <div className="admin-empty-state"><h2>No working ceremony selected</h2><p>Choose a ceremony workspace before preparing announcement audio.</p><Link className="admin-button admin-button-primary" href="/admin/ceremonies">Open ceremonies</Link></div>}

            {ceremony && <>
                <section className="announcement-task-list" aria-label="Announcement preparation">
                    <Link href="/admin/pronunciation-rules">
                        <span><strong>Pronunciation</strong><small>Names and wording</small></span>
                        <span>Open</span>
                    </Link>
                    <Link href={permissions.batches ? batchUrl : workspace.urls.graduates}>
                        <span><strong>Generate audio</strong><small>{counts.missing} {counts.missing === 1 ? 'graduate needs audio' : 'graduates need audio'}</small></span>
                        <span>Open</span>
                    </Link>
                    {permissions.review && reviewQueue.length > 0 && (
                        <a href="#review-queue">
                            <span><strong>Review audio</strong><small>{counts.needs_review} waiting</small></span>
                            <span>Review</span>
                        </a>
                    )}
                </section>

                {permissions.review && reviewQueue.length > 0 && (
                    <section className="voice-review-queue" id="review-queue">
                        <div className="admin-section-heading">
                            <div>
                                <h2>Review audio</h2>
                            </div>
                            <strong>{counts.needs_review} waiting</strong>
                        </div>
                        <ol>
                                {reviewQueue.map((candidate, index) => (
                                    <li key={candidate.id}>
                                        <span>{index + 1}</span>
                                        <div><strong>{candidate.student.name}</strong><small>Sequence {candidate.student.sequence_number} · {candidate.provider === 'kokoro' ? 'Local offline' : 'Azure online'} · {candidate.voice}</small></div>
                                        <Link className={index === 0 ? 'admin-button admin-button-primary' : 'admin-row-action'} href={candidate.review_url}>{index === 0 ? 'Review next' : 'Review'}</Link>
                                    </li>
                                ))}
                        </ol>
                    </section>
                )}

                <details className="announcement-advanced-settings">
                    <summary>Advanced announcement settings</summary>
                    <div className="voice-provider-readiness" aria-label="Voice provider readiness">
                        <article><span>Local offline</span><strong>{providers.kokoro.configured ? 'Ready' : 'Setup required'}</strong><p>Runs locally and does not need internet at generation time.</p></article>
                        <article><span>Azure online</span><strong>{providers.azure.configured ? 'Ready' : 'Setup required'}</strong><p>Uses configured Azure credentials and internet connectivity.</p></article>
                    </div>
                    {permissions.settings && <Link href={settingsUrl}>Open announcement settings</Link>}
                </details>
            </>}
        </AdminLayout>
    );
}
