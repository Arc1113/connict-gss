import { Head, Link, usePage } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import EmptyState from '../../../components/Admin/EmptyState';
import PageHeader from '../../../components/Admin/PageHeader';
import Pagination from '../../../components/Admin/Pagination';
import StatusText from '../../../components/Admin/StatusText';

export default function StudentIndex({
    filterOptions,
    filters,
    students,
}) {
    const { auth } = usePage().props;
    const hasFilters = Object.entries(filters).some(
        ([key, value]) => key !== 'sort' && Boolean(value),
    );
    const advancedFilterCount = [
        filters.college,
        filters.program,
        filters.stage_status,
        filters.sort && filters.sort !== 'sequence' ? filters.sort : '',
    ].filter(Boolean).length;

    return (
        <AdminLayout>
            <Head title="Graduate records" />
            <PageHeader
                actions={
                    (auth.abilities.import_students ||
                        auth.abilities.manage_students) && (
                        <>
                            {auth.abilities.import_students && (
                                <Link
                                    className="admin-button admin-button-secondary"
                                    href="/admin/students/import"
                                >
                                    Import CSV
                                </Link>
                            )}
                            {auth.abilities.manage_students && (
                                <Link
                                    className="admin-button admin-button-primary"
                                    href="/admin/students/create"
                                >
                                    Add graduate
                                </Link>
                            )}
                        </>
                    )
                }
                description="Find and correct preparation data without exposing QR tokens."
                eyebrow="Administration"
                title="Graduate records"
            />

            <form action="/admin/students" className="admin-filters" method="get">
                <div className="admin-filter-search admin-filter-search-wide">
                    <label htmlFor="search">Search name or student number</label>
                    <input
                        defaultValue={filters.search}
                        data-page-search
                        id="search"
                        name="search"
                        placeholder="Ana Marie dela Cruz or student number"
                        type="search"
                    />
                </div>
                <div>
                    <label htmlFor="ceremony_id">Ceremony</label>
                    <select
                        defaultValue={filters.ceremony_id}
                        id="ceremony_id"
                        name="ceremony_id"
                    >
                        <option value="">All ceremonies</option>
                        {filterOptions.ceremonies.map((ceremony) => (
                            <option key={ceremony.id} value={ceremony.id}>
                                {ceremony.name}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="eligibility_status">Eligibility</label>
                    <select
                        defaultValue={filters.eligibility_status}
                        id="eligibility_status"
                        name="eligibility_status"
                    >
                        <option value="">All eligibility states</option>
                        {filterOptions.eligibilityStatuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="media">Media readiness</label>
                    <select defaultValue={filters.media} id="media" name="media">
                        <option value="">All media states</option>
                        <option value="ready">Photo ready and audio approved</option>
                        <option value="missing_any">Any media missing</option>
                        <option value="missing_photo">Photo missing</option>
                        <option value="missing_audio">Audio missing</option>
                        <option value="audio_uploaded">Audio uploaded</option>
                        <option value="audio_needs_review">Audio needs review</option>
                        <option value="audio_approved">Audio approved</option>
                        <option value="audio_rejected">Audio rejected</option>
                    </select>
                </div>
                <details
                    className="admin-filter-advanced"
                    open={advancedFilterCount > 0}
                >
                    <summary>
                        More filters
                        {advancedFilterCount > 0 && (
                            <span>{advancedFilterCount} active</span>
                        )}
                    </summary>
                    <div className="admin-filter-advanced-grid">
                        <div>
                            <label htmlFor="college">College</label>
                            <select
                                defaultValue={filters.college}
                                id="college"
                                name="college"
                            >
                                <option value="">All colleges</option>
                                {filterOptions.colleges.map((college) => (
                                    <option key={college} value={college}>
                                        {college}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="program">Program</label>
                            <select
                                defaultValue={filters.program}
                                id="program"
                                name="program"
                            >
                                <option value="">All programs</option>
                                {filterOptions.programs.map((program) => (
                                    <option key={program} value={program}>
                                        {program}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="stage_status">Stage status</label>
                            <select
                                defaultValue={filters.stage_status}
                                id="stage_status"
                                name="stage_status"
                            >
                                <option value="">All stage states</option>
                                <option value="not_presented">Not presented</option>
                                <option value="presenting">Presenting</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div>
                            <label htmlFor="sort">Sort</label>
                            <select defaultValue={filters.sort} id="sort" name="sort">
                                <option value="sequence">Ceremony and sequence</option>
                                <option value="name">Display name</option>
                                <option value="student_number">Student number</option>
                            </select>
                        </div>
                    </div>
                </details>
                <div className="admin-filter-actions">
                    <button className="admin-button admin-button-primary" type="submit">
                        Apply filters
                    </button>
                    {hasFilters && (
                        <Link
                            className="admin-button admin-button-secondary"
                            href="/admin/students"
                        >
                            Clear
                        </Link>
                    )}
                </div>
            </form>

            {students.data.length === 0 ? (
                <EmptyState
                    action={
                        !hasFilters &&
                        auth.abilities.manage_students && (
                            <Link
                                className="admin-button admin-button-primary"
                                href="/admin/students/create"
                            >
                                Add the first graduate
                            </Link>
                        )
                    }
                    title={hasFilters ? 'No matching graduates' : 'No graduates yet'}
                >
                    {hasFilters
                        ? 'Change or clear the filters to review other graduate records.'
                        : 'Add an approved graduate record for a prepared ceremony.'}
                </EmptyState>
            ) : (
                <>
                    <div className="admin-table-toolbar" role="status">
                        <strong>{students.total} graduates</strong>
                        <span>
                            {hasFilters
                                ? 'Matching the current filters'
                                : 'All available graduate records'}
                        </span>
                    </div>
                    <div className="admin-table-wrap">
                        <table className="admin-table admin-student-table">
                            <caption className="sr-only">
                                Graduate records matching the current filters
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Sequence</th>
                                    <th scope="col">Graduate</th>
                                    <th scope="col">Ceremony</th>
                                    <th scope="col">Program</th>
                                    <th scope="col">Eligibility</th>
                                    <th scope="col">Media</th>
                                    <th scope="col">Stage</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {students.data.map((student) => (
                                    <tr key={student.id}>
                                        <td>{student.sequence_number}</td>
                                        <th scope="row">
                                            {student.display_name}
                                            <small>{student.student_number}</small>
                                        </th>
                                        <td>{student.ceremony.name}</td>
                                        <td>
                                            <span className="admin-cell-primary">
                                                {student.program}
                                            </span>
                                            <small>{student.college || 'College not set'}</small>
                                        </td>
                                        <td>
                                            <StatusText
                                                label={student.eligibility_status.label}
                                                value={student.eligibility_status.value}
                                            />
                                        </td>
                                        <td>
                                            <span className="admin-media-status-line">
                                                Photo: {student.media.photo}
                                            </span>
                                            <span className="admin-media-status-line">
                                                Audio: {student.media.audio}
                                            </span>
                                        </td>
                                        <td>{student.stage_status.label}</td>
                                        <td>
                                            <Link
                                                className="admin-row-action"
                                                href={`/admin/students/${student.id}`}
                                            >
                                                View
                                            </Link>
                                            {auth.abilities.prepare_qr_codes && (
                                                <Link
                                                    className="admin-row-action"
                                                    href={`/admin/students/${student.id}/qr-code`}
                                                >
                                                    QR card
                                                </Link>
                                            )}
                                            {auth.abilities.manage_students && (
                                                <Link
                                                    className="admin-row-action"
                                                    href={`/admin/students/${student.id}/edit`}
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
                    <Pagination collection={students} itemLabel="graduates" />
                </>
            )}
        </AdminLayout>
    );
}
