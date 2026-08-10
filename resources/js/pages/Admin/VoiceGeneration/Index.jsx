import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import PageHeader from '../../../components/Admin/PageHeader';

export default function VoiceGeneration({ workspace }) {
    const { ceremony, counts, permissions, providers, review_queue: reviewQueue } = workspace;
    const settingsUrl = ceremony ? `/admin/ceremonies/${ceremony.id}/announcement-settings` : '/admin/ceremonies';
    const batchUrl = ceremony ? `/admin/ceremonies/${ceremony.id}/announcement-batches` : '/admin/ceremonies';

    return (
        <AdminLayout>
            <Head title="Voice generation" />
            <PageHeader
                description="Choose a provider, verify pronunciation, generate candidates, and approve every announcement before ceremony day."
                eyebrow="Preparation workspace"
                title="Voice generation"
            />

            {!ceremony && <div className="admin-empty-state"><h2>No active ceremony</h2><p>Activate one ceremony before preparing announcement audio.</p><Link className="admin-button admin-button-primary" href="/admin/ceremonies">Open ceremonies</Link></div>}

            {ceremony && <>
                <section className="voice-workspace-summary">
                    <div><span>Active ceremony</span><strong>{ceremony.name}</strong></div>
                    <div><span>Awaiting generation</span><strong>{counts.missing}</strong></div>
                    <div><span>Awaiting review</span><strong>{counts.needs_review}</strong></div>
                    <div><span>Approved</span><strong>{counts.approved}</strong></div>
                </section>

                {permissions.review && (
                    <section className="voice-review-queue" id="review-queue">
                        <div className="admin-section-heading">
                            <div>
                                <p className="announcement-kicker">Human listening check</p>
                                <h2>Generated voices awaiting approval</h2>
                                <p>This queue follows ceremony sequence. Approve or reject one voice and the next graduate opens automatically.</p>
                            </div>
                            <strong>{counts.needs_review} waiting</strong>
                        </div>
                        {reviewQueue.length === 0 ? (
                            <div className="voice-review-complete"><strong>Review queue complete</strong><span>No generated voices currently need a decision.</span></div>
                        ) : (
                            <ol>
                                {reviewQueue.map((candidate, index) => (
                                    <li key={candidate.id}>
                                        <span>{index + 1}</span>
                                        <div><strong>{candidate.student.name}</strong><small>Sequence {candidate.student.sequence_number} · {candidate.provider === 'kokoro' ? 'Local offline' : 'Azure online'} · {candidate.voice}</small></div>
                                        <Link className={index === 0 ? 'admin-button admin-button-primary' : 'admin-row-action'} href={candidate.review_url}>{index === 0 ? 'Review next' : 'Review'}</Link>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                )}

                <section className="voice-provider-readiness" aria-label="Voice provider readiness">
                    <article><span>Local offline</span><strong>{providers.kokoro.configured ? 'Ready' : 'Setup required'}</strong><p>Runs Kokoro on this computer and does not need internet at generation time.</p></article>
                    <article><span>Azure online</span><strong>{providers.azure.configured ? 'Ready' : 'Setup required'}</strong><p>Uses configured Azure credentials and internet connectivity.</p></article>
                </section>

                <ol className="voice-workflow">
                    <li><span>1</span><div><h2>Select Local or Azure</h2><p>The provider is saved on a new immutable ceremony template version.</p>{permissions.settings && <Link href={settingsUrl}>Open provider settings</Link>}</div></li>
                    <li><span>2</span><div><h2>Compare curated voices</h2><p>Generate temporary samples using a realistic fictional graduation announcement.</p>{permissions.settings && <Link href={`${settingsUrl}#voice-comparison`}>Compare voices</Link>}</div></li>
                    <li><span>3</span><div><h2>Preview wording and pronunciation</h2><p>Open a graduate to confirm their spoken name, awards, and announcement text.</p><Link href="/admin/students">Open graduates</Link></div></li>
                    <li><span>4</span><div><h2>Generate selected or batch audio</h2><p>Create private candidates without approving or publishing them automatically.</p>{permissions.batches && <Link href={batchUrl}>Start batch generation</Link>}</div></li>
                    <li><span>5</span><div><h2>Review and approve</h2><p>{counts.needs_review} candidate{counts.needs_review === 1 ? '' : 's'} currently need a human listening check.</p>{permissions.review && <a href="#review-queue">Open review queue</a>}</div></li>
                </ol>
            </>}
        </AdminLayout>
    );
}
