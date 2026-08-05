import { Head, Link, router, useForm } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import { ValidationSummary } from '../../../components/Admin/FormSupport';
import PageHeader from '../../../components/Admin/PageHeader';
import StatusText from '../../../components/Admin/StatusText';
import { getEcho } from '../../../lib/echo';

const ACTIVE_STATUSES = new Set(['queued', 'running', 'paused']);

function formatDateTime(value) {
    if (!value) return 'Not recorded';
    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function statusLabel(batch) {
    if (batch.cancellation_requested_at && batch.status === 'running') return 'Cancelling';
    if (batch.status === 'completed') {
        return batch.failed_count > 0 ? 'Preparation complete with failures' : 'Preparation complete';
    }
    return batch.status.replace('_', ' ');
}

function BatchCard({ batch, ceremonyId, providerReady }) {
    const [pendingAction, setPendingAction] = useState(null);
    const percentage = batch.selected_count === 0
        ? 0
        : Math.round((batch.processed_count / batch.selected_count) * 100);

    function cancel() {
        if (!window.confirm('Cancel all queued items? An in-flight provider request will finish safely.')) return;
        setPendingAction('cancel');
        router.post(`/admin/ceremonies/${ceremonyId}/announcement-batches/${batch.id}/cancel`, {}, {
            preserveScroll: true,
            onFinish: () => setPendingAction(null),
        });
    }

    function resume() {
        if (!window.confirm('Resume this batch on the sequential preparation worker?')) return;
        setPendingAction('resume');
        router.post(`/admin/ceremonies/${ceremonyId}/announcement-batches/${batch.id}/resume`, {}, {
            preserveScroll: true,
            onFinish: () => setPendingAction(null),
        });
    }

    return (
        <article className="announcement-batch-card">
            <header>
                <div>
                    <p className="announcement-kicker">Batch #{batch.id} · template v{batch.template.version}</p>
                    <h3>{batch.template.name}</h3>
                    <span>{batch.voice} · {batch.locale} · requested by {batch.requested_by ?? 'Former operator'}</span>
                </div>
                <StatusText label={statusLabel(batch)} value={batch.status === 'completed' ? 'approved' : batch.status === 'paused' ? 'warning' : batch.status === 'cancelled' ? 'rejected' : 'pending'} />
            </header>

            <div className="announcement-batch-progress" aria-label={`${batch.processed_count} of ${batch.selected_count} processed`}>
                <span style={{ width: `${percentage}%` }} />
            </div>
            <dl className="announcement-batch-counts">
                <div><dt>Processed</dt><dd>{batch.processed_count}/{batch.selected_count}</dd></div>
                <div><dt>New candidates</dt><dd>{batch.succeeded_count}</dd></div>
                <div><dt>Reused candidates</dt><dd>{batch.reused_count}</dd></div>
                <div><dt>Failed</dt><dd>{batch.failed_count}</dd></div>
                <div><dt>Estimate</dt><dd>{batch.estimated_character_count.toLocaleString()} chars</dd></div>
            </dl>

            {batch.failure && <p className="announcement-notice announcement-notice-warning" role="status">{batch.failure.message}</p>}

            <div className="admin-form-actions">
                {ACTIVE_STATUSES.has(batch.status) && !batch.cancellation_requested_at && (
                    <button className="admin-button admin-button-danger" disabled={pendingAction !== null} onClick={cancel} type="button">
                        {pendingAction === 'cancel' ? 'Cancelling…' : 'Cancel queued items'}
                    </button>
                )}
                {batch.status === 'paused' && (
                    <button className="admin-button admin-button-primary" disabled={pendingAction !== null || !providerReady || batch.failure?.code === 'settings_changed'} onClick={resume} type="button">
                        {pendingAction === 'resume' ? 'Resuming…' : 'Resume batch'}
                    </button>
                )}
            </div>

            {batch.status === 'completed' && (
                <p className={`announcement-batch-review-summary${batch.awaiting_review_count > 0 ? ' announcement-batch-review-summary-pending' : ''}`}>
                    <strong>{batch.awaiting_review_count} awaiting review</strong>
                    <span>{batch.approved_count} approved for stage playback</span>
                </p>
            )}

            <details>
                <summary>View item results and review links</summary>
                <ol className="announcement-batch-items">
                    {batch.items.map((item) => (
                        <li key={item.id}>
                            <span><strong>{item.position}. {item.student.display_name}</strong><small>Sequence {item.student.sequence_number} · {item.character_count} characters</small></span>
                            <span className="announcement-batch-item-status">
                                <small>Preparation: {item.status.replace('_', ' ')}</small>
                                {item.generation && <strong>Review: {item.generation.status_label}</strong>}
                            </span>
                            {item.generation?.status === 'needs_review' && (
                                <Link className="admin-button admin-button-secondary" href={`/admin/students/${item.student.id}#announcement-candidate-${item.generation.id}`}>
                                    Review candidate
                                </Link>
                            )}
                            {item.failure_message && <small>{item.failure_message}</small>}
                        </li>
                    ))}
                </ol>
            </details>
            <footer>Queued {formatDateTime(batch.created_at)}{batch.completed_at ? ` · finished ${formatDateTime(batch.completed_at)}` : ''}</footer>
        </article>
    );
}

export default function AnnouncementBatches({ workspace }) {
    const { ceremony, limits, provider, template, usage } = workspace;
    const form = useForm({ student_ids: [], budget_acknowledged: false });
    const [refreshing, setRefreshing] = useState(false);
    const refreshLock = useRef(false);
    const selected = new Set(form.data.student_ids);
    const selectedStudents = workspace.students.filter((student) => selected.has(student.id));
    const selectedCharacters = selectedStudents.reduce((sum, student) => sum + student.character_count, 0);
    const projectedUsage = usage.successful_characters + selectedCharacters;
    const budgetWarning = usage.monthly_budget > 0 && projectedUsage >= usage.warning_threshold;
    const readyStudents = workspace.students.filter((student) => student.ready);
    const activeBatchCount = workspace.batches.filter((batch) => ACTIVE_STATUSES.has(batch.status)).length;
    const canStart = provider.configured
        && template
        && selectedStudents.length > 0
        && selectedStudents.length <= limits.maximum_students
        && selectedStudents.every((student) => student.ready)
        && (!budgetWarning || form.data.budget_acknowledged);
    const startGuidance = !provider.configured
        ? 'Audio generation is unavailable until the speech provider is configured.'
        : !template
          ? 'Publish announcement settings before generating audio.'
          : selectedStudents.length === 0
            ? 'Select at least one ready graduate.'
            : selectedStudents.length > limits.maximum_students
              ? `Reduce the selection to ${limits.maximum_students} graduates.`
              : !selectedStudents.every((student) => student.ready)
                ? 'Remove blocked graduates before starting.'
                : budgetWarning && !form.data.budget_acknowledged
                  ? 'Acknowledge the budget warning to continue.'
                  : 'Ready to generate private review candidates.';

    const reloadWorkspace = useCallback((showSpinner = false) => {
        if (refreshLock.current) return;

        refreshLock.current = true;

        if (showSpinner) {
            setRefreshing(true);
        }

        router.reload({
            only: ['workspace'],
            preserveScroll: true,
            onFinish: () => {
                refreshLock.current = false;
                setRefreshing(false);
            },
        });
    }, []);

    function refreshProgress() {
        reloadWorkspace(true);
    }

    useEffect(() => {
        const echo = getEcho();
        if (!echo) return undefined;
        const channelName = `announcement-batches.${ceremony.id}`;
        echo.private(channelName).listen('.announcement-batch.updated', () => {
            reloadWorkspace();
        });

        return () => echo.leave(channelName);
    }, [ceremony.id, reloadWorkspace]);

    useEffect(() => {
        if (activeBatchCount === 0) return undefined;

        const intervalId = window.setInterval(() => {
            if (document.visibilityState !== 'visible') return;

            reloadWorkspace();
        }, 5000);

        return () => window.clearInterval(intervalId);
    }, [activeBatchCount, reloadWorkspace]);

    function toggleStudent(id) {
        form.setData('student_ids', selected.has(id)
            ? form.data.student_ids.filter((studentId) => studentId !== id)
            : [...form.data.student_ids, id]);
    }

    function selectReady() {
        form.setData('student_ids', readyStudents
            .slice(0, limits.maximum_students)
            .map((student) => student.id));
    }

    function submit(event) {
        event.preventDefault();
        if (!window.confirm(`Queue ${selectedStudents.length} graduates in ceremony sequence order? Candidates will still require individual review and approval.`)) return;
        form.post(`/admin/ceremonies/${ceremony.id}/announcement-batches`, {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <AdminLayout>
            <Head title={`Announcement batches · ${ceremony.name}`} />
            <PageHeader
                actions={(
                    <>
                        <Link className="admin-button admin-button-secondary" href={`/admin/ceremonies/${ceremony.id}/announcement-settings`}>Announcement settings</Link>
                        <Link className="admin-button admin-button-secondary" href={`/admin/ceremonies/${ceremony.id}`}>Back to ceremony</Link>
                    </>
                )}
                description="Queue a bounded, deterministic set of private candidates on the preparation-only worker."
                eyebrow="Automated announcements"
                title={`${ceremony.name} batch generation`}
            />

            <ValidationSummary errors={form.errors} />

            <section className="admin-detail-section announcement-batch-summary">
                <div className="admin-section-heading">
                    <div>
                        <h2>Preparation boundary</h2>
                        <p>Processing is sequential with persisted progress. Generated candidates are never approved automatically, and the ceremony does not depend on this worker.</p>
                    </div>
                    <StatusText label={provider.configured ? 'Provider ready' : 'Provider disabled'} value={provider.configured ? 'approved' : 'missing'} />
                </div>
                <dl className="admin-detail-list admin-detail-list-compact">
                    <div><dt>Template</dt><dd>{template ? `${template.name} · v${template.version}` : 'Publish settings first'}</dd></div>
                    <div><dt>Voice</dt><dd>{template ? `${template.voice} · ${template.locale}` : 'Not selected'}</dd></div>
                    <div><dt>Batch limit</dt><dd>{limits.maximum_students} graduates</dd></div>
                    <div><dt>Monthly estimate</dt><dd>{usage.successful_characters.toLocaleString()} characters</dd></div>
                </dl>
            </section>

            <form className="admin-detail-section" onSubmit={submit}>
                <div className="admin-section-heading">
                    <div><h2>Select graduates</h2><p>Only ceremony-owned, non-ineligible records with a ready preview can be queued.</p></div>
                    <div className="admin-form-actions">
                        <button className="admin-button admin-button-secondary" disabled={readyStudents.length === 0} onClick={selectReady} type="button">Select first {Math.min(readyStudents.length, limits.maximum_students)} ready</button>
                        <button className="admin-button admin-button-secondary" disabled={selectedStudents.length === 0} onClick={() => form.setData('student_ids', [])} type="button">Clear selection</button>
                    </div>
                </div>

                <div className="admin-table-wrap">
                    <table className="admin-table announcement-batch-selection">
                        <thead><tr><th scope="col">Select</th><th scope="col">Sequence</th><th scope="col">Graduate</th><th scope="col">Program</th><th scope="col">Estimate</th><th scope="col">Preview</th></tr></thead>
                        <tbody>
                            {workspace.students.map((student) => (
                                <tr key={student.id}>
                                    <td data-label="Select"><input aria-label={`Select ${student.display_name}`} checked={selected.has(student.id)} disabled={!student.ready} onChange={() => toggleStudent(student.id)} type="checkbox" /></td>
                                    <td data-label="Sequence">{student.sequence_number}</td>
                                    <th data-label="Graduate" scope="row">{student.display_name}<small>{student.eligibility_status}</small></th>
                                    <td data-label="Program">{student.program || 'Program missing'}</td>
                                    <td data-label="Estimate">{student.character_count.toLocaleString()} characters</td>
                                    <td data-label="Preview"><StatusText label={student.ready ? 'Ready' : 'Blocked'} value={student.ready ? 'ready' : 'rejected'} /></td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {limits.candidate_pool_truncated && <p className="announcement-notice announcement-notice-warning">Only the first 500 ceremony records are shown. Narrow the ceremony data before batch preparation.</p>}

                <div className="announcement-batch-estimate">
                    <div><strong>{selectedStudents.length} selected</strong><span>{selectedCharacters.toLocaleString()} proposed characters</span></div>
                    <div><strong>{projectedUsage.toLocaleString()} projected this month</strong><span>{usage.label}</span></div>
                </div>
                {selectedStudents.length > limits.maximum_students && <p className="announcement-notice announcement-notice-error" role="alert">Reduce the selection to {limits.maximum_students} graduates.</p>}
                {budgetWarning && (
                    <label className="admin-checkbox-row announcement-budget-acknowledgement">
                        <input checked={form.data.budget_acknowledged} onChange={(event) => form.setData('budget_acknowledged', event.target.checked)} type="checkbox" />
                        I acknowledge that this local estimate reaches the configured warning threshold. Azure billing remains authoritative.
                    </label>
                )}
                <div className="announcement-batch-submit">
                    <button className="admin-button admin-button-primary" disabled={!canStart || form.processing} type="submit">{form.processing ? 'Starting batch…' : 'Generate selected audio'}</button>
                    <p aria-live="polite" className="admin-field-hint">{startGuidance}</p>
                </div>
            </form>

            <section className="admin-detail-section">
                <div className="admin-section-heading"><div><h2>Batch history and review</h2><p>Generation completion is separate from approval. Open item results to review each playable candidate.{activeBatchCount > 0 ? ' Progress now refreshes automatically while preparation is active, with manual refresh kept as a fallback.' : ''}</p></div><button className="admin-button admin-button-secondary" disabled={refreshing} onClick={refreshProgress} type="button">{refreshing ? 'Refreshing…' : activeBatchCount > 0 ? 'Refresh now' : 'Refresh progress'}</button></div>
                {workspace.batches.length === 0 ? <p className="admin-media-missing">No announcement batches have been queued.</p> : (
                    <div className="announcement-batch-list">
                        {workspace.batches.map((batch) => <BatchCard batch={batch} ceremonyId={ceremony.id} key={batch.id} providerReady={provider.configured} />)}
                    </div>
                )}
            </section>
        </AdminLayout>
    );
}
