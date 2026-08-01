import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import EmptyState from '../../../components/Admin/EmptyState';
import PageHeader from '../../../components/Admin/PageHeader';
import Pagination from '../../../components/Admin/Pagination';
import StatusText from '../../../components/Admin/StatusText';

function formatDateTime(value) {
    if (!value) return 'Not recorded';

    return new Intl.DateTimeFormat('en-PH', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function exportHref(filters) {
    const parameters = new URLSearchParams(
        Object.entries(filters).filter(([, value]) => value),
    );

    return `/admin/scan-logs/export?${parameters.toString()}`;
}

export default function ScanLogs({ canExport, filterOptions, filters, logs }) {
    const hasFilters = Object.values(filters).some(Boolean);

    return (
        <AdminLayout>
            <Head title="Scan logs" />
            <PageHeader
                actions={
                    <>
                        <Link
                            className="admin-button admin-button-secondary"
                            href="/admin/stage-events"
                        >
                            Stage events
                        </Link>
                        {canExport && (
                            <a className="admin-button admin-button-primary" href={exportHref(filters)}>
                                Export CSV
                            </a>
                        )}
                    </>
                }
                description="Filtered scanner attempts with safe results and no raw QR values."
                eyebrow="Operational logs"
                title="Scan logs"
            />

            <form action="/admin/scan-logs" className="admin-filters" method="get">
                <div>
                    <label htmlFor="ceremony_id">Ceremony</label>
                    <select defaultValue={filters.ceremony_id} id="ceremony_id" name="ceremony_id">
                        <option value="">All allowed ceremonies</option>
                        {filterOptions.ceremonies.map((ceremony) => (
                            <option key={ceremony.id} value={ceremony.id}>
                                {ceremony.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="result">Result</label>
                    <select defaultValue={filters.result} id="result" name="result">
                        <option value="">All results</option>
                        {filterOptions.results.map((result) => (
                            <option key={result.value} value={result.value}>
                                {result.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="outcome">Valid/rejected</label>
                    <select defaultValue={filters.outcome} id="outcome" name="outcome">
                        <option value="">All outcomes</option>
                        <option value="valid">Accepted only</option>
                        <option value="rejected">Rejected attempts</option>
                    </select>
                </div>
                <div>
                    <label htmlFor="operator_id">Operator</label>
                    <select defaultValue={filters.operator_id} id="operator_id" name="operator_id">
                        <option value="">All operators</option>
                        {filterOptions.operators.map((operator) => (
                            <option key={operator.id} value={operator.id}>
                                {operator.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="admin-filter-search">
                    <label htmlFor="graduate">Graduate</label>
                    <input
                        defaultValue={filters.graduate}
                        data-page-search
                        id="graduate"
                        name="graduate"
                        placeholder="Ana Marie dela Cruz"
                        type="search"
                    />
                </div>
                <div>
                    <label htmlFor="from">From</label>
                    <input defaultValue={filters.from} id="from" name="from" type="datetime-local" />
                </div>
                <div>
                    <label htmlFor="to">To</label>
                    <input defaultValue={filters.to} id="to" name="to" type="datetime-local" />
                </div>
                <div className="admin-filter-actions">
                    <button className="admin-button admin-button-primary" type="submit">
                        Apply filters
                    </button>
                    {hasFilters && (
                        <Link className="admin-button admin-button-secondary" href="/admin/scan-logs">
                            Clear
                        </Link>
                    )}
                </div>
            </form>

            {logs.data.length === 0 ? (
                <EmptyState title="No scan attempts">
                    {hasFilters
                        ? 'No safe scan records match the current filters.'
                        : 'Scanner attempts will appear here after operators submit QR input.'}
                </EmptyState>
            ) : (
                <>
                    <div className="admin-table-toolbar" role="status">
                        <strong>{logs.total} scan attempts</strong>
                        <span>{hasFilters ? 'Filtered evidence' : 'All allowed evidence'}</span>
                    </div>
                    <div className="admin-table-wrap">
                        <table className="admin-table">
                            <caption className="sr-only">Filtered scan log results</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Time</th>
                                    <th scope="col">Result</th>
                                    <th scope="col">Graduate</th>
                                    <th scope="col">Ceremony</th>
                                    <th scope="col">Operator</th>
                                    <th scope="col">Safe message</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.data.map((log) => (
                                    <tr key={log.id}>
                                        <td>{formatDateTime(log.time)}</td>
                                        <td>
                                            <StatusText label={log.result.label} value={log.result.value} />
                                        </td>
                                        <td>{log.graduate}</td>
                                        <td>{log.ceremony}</td>
                                        <td>{log.operator}</td>
                                        <td>
                                            {log.message || log.queue_outcome}
                                            {log.message && log.message !== log.queue_outcome && (
                                                <small className="admin-table-note">{log.queue_outcome}</small>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination collection={logs} itemLabel="scan attempts" />
                </>
            )}
        </AdminLayout>
    );
}
