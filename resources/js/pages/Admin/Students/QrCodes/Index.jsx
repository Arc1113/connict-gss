import { Head, Link } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminLayout from '../../../../components/Admin/AdminLayout';
import EmptyState from '../../../../components/Admin/EmptyState';
import PageHeader from '../../../../components/Admin/PageHeader';
import Pagination from '../../../../components/Admin/Pagination';
import StatusText from '../../../../components/Admin/StatusText';

function filterValue(value) {
    return value ?? '';
}

export default function StudentQrCodeIndex({
    csrfToken,
    filterOptions,
    filters,
    maximumBatchSize,
    students,
}) {
    const [selectedIds, setSelectedIds] = useState([]);
    const selectableIds = useMemo(
        () =>
            students.data
                .filter((student) => student.qr.printable)
                .map((student) => student.id),
        [students.data],
    );
    const allPrintableSelected =
        selectableIds.length > 0 &&
        selectableIds.every((id) => selectedIds.includes(id));

    function toggleStudent(id) {
        setSelectedIds((current) => {
            if (current.includes(id)) {
                return current.filter((selectedId) => selectedId !== id);
            }

            return current.length >= maximumBatchSize ? current : [...current, id];
        });
    }

    function togglePrintablePage() {
        setSelectedIds((current) => {
            if (allPrintableSelected) {
                return current.filter((id) => !selectableIds.includes(id));
            }

            return [...new Set([...current, ...selectableIds])].slice(
                0,
                maximumBatchSize,
            );
        });
    }

    return (
        <AdminLayout>
            <Head title="QR preparation" />
            <PageHeader
                actions={
                    <Link
                        className="admin-button admin-button-secondary"
                        href="/admin/students"
                    >
                        Graduate records
                    </Link>
                }
                description="Review readiness, inspect individual cards, and prepare controlled print batches without exposing encoded tokens."
                eyebrow="Graduate data preparation"
                title="QR preparation"
            />

            <section className="admin-qr-instructions" aria-labelledby="qr-workflow">
                <h2 id="qr-workflow">Print workflow</h2>
                <ol>
                    <li>Filter one ceremony and review each graduate’s readiness.</li>
                    <li>Select up to {maximumBatchSize} printable graduates.</li>
                    <li>Open the print sheet, use 100% scale, and disable headers and footers.</li>
                    <li>Test representative printed cards on the scanner before the ceremony.</li>
                </ol>
            </section>

            <form
                action="/admin/students/qr-codes"
                className="admin-filters admin-qr-filters"
                method="get"
            >
                <div className="admin-filter-search-wide">
                    <label htmlFor="ceremony_id">Ceremony</label>
                    <select
                        defaultValue={filterValue(filters.ceremony_id)}
                        id="ceremony_id"
                        name="ceremony_id"
                    >
                        {filterOptions.ceremonies.length === 0 && (
                            <option value="">No ceremonies available</option>
                        )}
                        {filterOptions.ceremonies.map((ceremony) => (
                            <option key={ceremony.id} value={ceremony.id}>
                                {ceremony.name}
                                {ceremony.academic_year
                                    ? ` — ${ceremony.academic_year}`
                                    : ''}{' '}
                                ({ceremony.status})
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="college">College</label>
                    <select
                        defaultValue={filterValue(filters.college)}
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
                        defaultValue={filterValue(filters.program)}
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
                    <label htmlFor="eligibility_status">Eligibility</label>
                    <select
                        defaultValue={filterValue(filters.eligibility_status)}
                        id="eligibility_status"
                        name="eligibility_status"
                    >
                        <option value="">All states</option>
                        <option value="eligible">Eligible</option>
                        <option value="pending">Pending</option>
                        <option value="ineligible">Ineligible</option>
                    </select>
                </div>
                <div>
                    <label htmlFor="qr_readiness">Token state</label>
                    <select
                        defaultValue={filterValue(filters.qr_readiness)}
                        id="qr_readiness"
                        name="qr_readiness"
                    >
                        <option value="">All token states</option>
                        <option value="ready">Original token</option>
                        <option value="regenerated">Regenerated — reprint</option>
                    </select>
                </div>
                <div>
                    <label htmlFor="sequence_from">Sequence from</label>
                    <input
                        defaultValue={filterValue(filters.sequence_from)}
                        id="sequence_from"
                        min="1"
                        name="sequence_from"
                        type="number"
                    />
                </div>
                <div>
                    <label htmlFor="sequence_to">Sequence to</label>
                    <input
                        defaultValue={filterValue(filters.sequence_to)}
                        id="sequence_to"
                        min="1"
                        name="sequence_to"
                        type="number"
                    />
                </div>
                <div className="admin-filter-actions">
                    <button className="admin-button admin-button-primary" type="submit">
                        Apply filters
                    </button>
                    <Link
                        className="admin-button admin-button-secondary"
                        href="/admin/students/qr-codes"
                    >
                        Reset
                    </Link>
                </div>
            </form>

            {students.data.length === 0 ? (
                <EmptyState title="No graduates match this preparation view">
                    Change the ceremony or filters. Graduate records are managed
                    separately from this print workspace.
                </EmptyState>
            ) : (
                <form
                    action="/admin/students/qr-codes/batch"
                    className="admin-qr-batch"
                    method="post"
                    target="_blank"
                >
                    <input name="_token" type="hidden" value={csrfToken} />
                    <input
                        name="ceremony_id"
                        type="hidden"
                        value={filterValue(filters.ceremony_id)}
                    />
                    {[
                        'college',
                        'program',
                        'eligibility_status',
                        'qr_readiness',
                        'sequence_from',
                        'sequence_to',
                    ].map((field) => (
                        <input
                            key={field}
                            name={field}
                            type="hidden"
                            value={filterValue(filters[field])}
                        />
                    ))}
                    {selectedIds.map((id) => (
                        <input
                            key={id}
                            name="student_ids[]"
                            type="hidden"
                            value={id}
                        />
                    ))}

                    <div className="admin-qr-selection-bar">
                        <div>
                            <strong>
                                {selectedIds.length}{' '}
                                {selectedIds.length === 1 ? 'graduate' : 'graduates'}{' '}
                                selected
                            </strong>
                            <span>
                                Selection applies to this result page. Changing filters
                                or pages starts a new selection; the batch limit is{' '}
                                {maximumBatchSize}.
                            </span>
                        </div>
                        <button
                            className="admin-button admin-button-secondary"
                            disabled={selectableIds.length === 0}
                            onClick={togglePrintablePage}
                            type="button"
                        >
                            {allPrintableSelected
                                ? 'Clear current page'
                                : 'Select printable page'}
                        </button>
                        <button
                            className="admin-button admin-button-primary"
                            disabled={selectedIds.length === 0}
                            type="submit"
                        >
                            Open batch print sheet
                        </button>
                    </div>

                    <div className="admin-table-wrap">
                        <table className="admin-table admin-qr-table">
                            <caption className="sr-only">
                                QR print readiness for the selected ceremony
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Select</th>
                                    <th scope="col">Sequence</th>
                                    <th scope="col">Graduate</th>
                                    <th scope="col">Program</th>
                                    <th scope="col">Eligibility</th>
                                    <th scope="col">QR readiness</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {students.data.map((student) => (
                                    <tr key={student.id}>
                                        <td>
                                            <input
                                                aria-label={`Select ${student.display_name} for batch printing`}
                                                checked={selectedIds.includes(student.id)}
                                                disabled={!student.qr.printable}
                                                onChange={() => toggleStudent(student.id)}
                                                type="checkbox"
                                            />
                                        </td>
                                        <td>{student.sequence_number}</td>
                                        <th scope="row">
                                            {student.display_name}
                                            <small>{student.student_number}</small>
                                        </th>
                                        <td>
                                            <span className="admin-cell-primary">
                                                {student.program}
                                            </span>
                                            <small>{student.college || 'College not set'}</small>
                                        </td>
                                        <td>
                                            <StatusText
                                                label={student.eligibility.label}
                                                value={student.eligibility.value}
                                            />
                                        </td>
                                        <td>
                                            <StatusText
                                                label={student.qr.label}
                                                value={student.qr.status}
                                            />
                                            {!student.qr.printable && (
                                                <small>
                                                    {student.qr.print_blockers.join(' ')}
                                                </small>
                                            )}
                                        </td>
                                        <td>
                                            <Link
                                                className="admin-row-action"
                                                href={`/admin/students/${student.id}/qr-code`}
                                            >
                                                Review card
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination collection={students} itemLabel="graduates" />
                </form>
            )}
        </AdminLayout>
    );
}
