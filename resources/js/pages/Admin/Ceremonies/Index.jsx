import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import EmptyState from '../../../components/Admin/EmptyState';
import PageHeader from '../../../components/Admin/PageHeader';
import Pagination from '../../../components/Admin/Pagination';
import StatusText from '../../../components/Admin/StatusText';

export default function CeremonyIndex({
    academicYears,
    ceremonies,
    filters,
    statusOptions,
}) {
    const { auth } = usePage().props;
    const hasFilters = Object.values(filters).some(Boolean);

    return (
        <AdminLayout>
            <Head title="Ceremonies" />
            <PageHeader
                actions={
                    auth.abilities.manage_ceremonies && (
                        <Link
                            className="admin-button admin-button-primary"
                            href="/admin/ceremonies/create"
                        >
                            Add ceremony
                        </Link>
                    )
                }
                description="Review ceremony identity, schedule, status, and presentation mode."
                eyebrow="Administration"
                title="Ceremonies"
            />

            <form action="/admin/ceremonies" className="admin-filters" method="get">
                <div className="admin-filter-search">
                    <label htmlFor="search">Search by ceremony name</label>
                    <input
                        defaultValue={filters.search}
                        data-page-search
                        id="search"
                        name="search"
                        placeholder="Enter a ceremony name"
                        type="search"
                    />
                </div>
                <div>
                    <label htmlFor="status">Status</label>
                    <select defaultValue={filters.status} id="status" name="status">
                        <option value="">All statuses</option>
                        {statusOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="academic_year">Academic year</label>
                    <select
                        defaultValue={filters.academic_year}
                        id="academic_year"
                        name="academic_year"
                    >
                        <option value="">All academic years</option>
                        {academicYears.map((year) => (
                            <option key={year} value={year}>
                                {year}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="admin-filter-actions">
                    <button className="admin-button admin-button-primary" type="submit">
                        Apply filters
                    </button>
                    {hasFilters && (
                        <Link
                            className="admin-button admin-button-secondary"
                            href="/admin/ceremonies"
                        >
                            Clear
                        </Link>
                    )}
                </div>
            </form>

            {ceremonies.data.length === 0 ? (
                <EmptyState
                    action={
                        !hasFilters &&
                        auth.abilities.manage_ceremonies && (
                            <Link
                                className="admin-button admin-button-primary"
                                href="/admin/ceremonies/create"
                            >
                                Add the first ceremony
                            </Link>
                        )
                    }
                    title={hasFilters ? 'No matching ceremonies' : 'No ceremonies yet'}
                >
                    {hasFilters
                        ? 'Change or clear the filters to review other ceremony records.'
                        : 'Create a ceremony before assigning graduate records.'}
                </EmptyState>
            ) : (
                <>
                    <div className="admin-table-toolbar" role="status">
                        <strong>{ceremonies.total} ceremonies</strong>
                        <span>
                            {hasFilters
                                ? 'Matching the current filters'
                                : 'All ceremony records'}
                        </span>
                    </div>
                    <div className="admin-table-wrap">
                        <table className="admin-table">
                            <caption className="sr-only">
                                Ceremony records matching the current filters
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Ceremony</th>
                                    <th scope="col">Academic year</th>
                                    <th scope="col">Date and venue</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Mode</th>
                                    <th scope="col">Graduates</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ceremonies.data.map((ceremony) => (
                                    <tr key={ceremony.id}>
                                        <th scope="row">{ceremony.name}</th>
                                        <td>{ceremony.academic_year || 'Not set'}</td>
                                        <td>
                                            <span className="admin-cell-primary">
                                                {ceremony.event_date || 'Date not set'}
                                            </span>
                                            <small>{ceremony.venue || 'Venue not set'}</small>
                                        </td>
                                        <td>
                                            <StatusText
                                                label={ceremony.status.label}
                                                value={ceremony.status.value}
                                            />
                                        </td>
                                        <td>{ceremony.presentation_mode.label}</td>
                                        <td>{ceremony.students_count}</td>
                                        <td>
                                            <Link
                                                className="admin-row-action"
                                                href={`/admin/ceremonies/${ceremony.id}`}
                                            >
                                                View
                                            </Link>
                                            {auth.abilities.manage_ceremonies && (
                                                <Link
                                                    className="admin-row-action"
                                                    href={`/admin/ceremonies/${ceremony.id}/edit`}
                                                >
                                                    Edit
                                                </Link>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination collection={ceremonies} itemLabel="ceremonies" />
                </>
            )}
        </AdminLayout>
    );
}
