import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import EmptyState from '../../../components/Admin/EmptyState';
import PageHeader from '../../../components/Admin/PageHeader';
import Pagination from '../../../components/Admin/Pagination';

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

    return `/admin/stage-events/export?${parameters.toString()}`;
}

export default function StageEvents({ canExport, events, filterOptions, filters }) {
    const hasFilters = Object.values(filters).some(Boolean);

    return (
        <AdminLayout mode="technical">
            <Head title="Stage events" />
            <PageHeader
                actions={
                    <>
                        <Link className="admin-button admin-button-secondary" href="/admin/scan-logs">
                            Scan logs
                        </Link>
                        {canExport && (
                            <a className="admin-button admin-button-primary" href={exportHref(filters)}>
                                Export CSV
                            </a>
                        )}
                    </>
                }
                description="Chronological queue, presentation, audio, holding, and reset events with safe details."
                eyebrow="Operational logs"
                title="Stage events"
            />

            <form action="/admin/stage-events" className="admin-filters" method="get">
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
                    <label htmlFor="event_type">Event type</label>
                    <select defaultValue={filters.event_type} id="event_type" name="event_type">
                        <option value="">All event types</option>
                        {filterOptions.eventTypes.map((eventType) => (
                            <option key={eventType.value} value={eventType.value}>
                                {eventType.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="actor_id">Actor</label>
                    <select defaultValue={filters.actor_id} id="actor_id" name="actor_id">
                        <option value="">All actors</option>
                        {filterOptions.actors.map((actor) => (
                            <option key={actor.id} value={actor.id}>
                                {actor.name}
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
                        placeholder="Christopher Emmanuel Villanueva-Santos"
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
                        <Link className="admin-button admin-button-secondary" href="/admin/stage-events">
                            Clear
                        </Link>
                    )}
                </div>
            </form>

            {events.data.length === 0 ? (
                <EmptyState title="No stage events">
                    {hasFilters
                        ? 'No stage events match the current filters.'
                        : 'Queue, presentation, replay, holding, and reset events will appear here.'}
                </EmptyState>
            ) : (
                <>
                    <div className="admin-table-toolbar" role="status">
                        <strong>{events.total} stage events</strong>
                        <span>{hasFilters ? 'Filtered evidence' : 'All allowed evidence'}</span>
                    </div>
                    <div className="admin-table-wrap">
                        <table className="admin-table">
                            <caption className="sr-only">Filtered stage events</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Time</th>
                                    <th scope="col">Event</th>
                                    <th scope="col">Graduate</th>
                                    <th scope="col">Actor</th>
                                    <th scope="col">Description</th>
                                    <th scope="col">Version</th>
                                </tr>
                            </thead>
                            <tbody>
                                {events.data.map((event) => (
                                    <tr key={event.id}>
                                        <td>{formatDateTime(event.time)}</td>
                                        <td>
                                            <code>{event.event_type.value}</code>
                                        </td>
                                        <td>{event.graduate}</td>
                                        <td>{event.actor}</td>
                                        <td>{event.description}</td>
                                        <td>{event.stage_version ?? '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination collection={events} itemLabel="stage events" />
                </>
            )}
        </AdminLayout>
    );
}
