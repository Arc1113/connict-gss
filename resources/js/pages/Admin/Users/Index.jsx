import { Head, Link } from '@inertiajs/react';
import AdminLayout from '../../../components/Admin/AdminLayout';
import EmptyState from '../../../components/Admin/EmptyState';
import PageHeader from '../../../components/Admin/PageHeader';
import Pagination from '../../../components/Admin/Pagination';
import StatusText from '../../../components/Admin/StatusText';

export default function UserIndex({ filters, roleOptions, users }) {
    const hasFilters = Object.values(filters).some(Boolean);

    return (
        <AdminLayout mode="technical">
            <Head title="Operator accounts" />
            <PageHeader
                actions={
                    <Link
                        className="admin-button admin-button-primary"
                        href="/admin/users/create"
                    >
                        Add operator account
                    </Link>
                }
                description="Create local role-based accounts before rehearsal. Passwords are never shown after saving."
                eyebrow="Access control"
                title="Operator accounts"
            />

            <form action="/admin/users" className="admin-filters" method="get">
                <div className="admin-filter-search">
                    <label htmlFor="search">Search name or email</label>
                    <input
                        defaultValue={filters.search}
                        data-page-search
                        id="search"
                        name="search"
                        placeholder="Enter an operator name or email"
                        type="search"
                    />
                </div>
                <div>
                    <label htmlFor="role">Role</label>
                    <select defaultValue={filters.role} id="role" name="role">
                        <option value="">All roles</option>
                        {roleOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>
                <div>
                    <label htmlFor="status">Account status</label>
                    <select
                        defaultValue={filters.status}
                        id="status"
                        name="status"
                    >
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div className="admin-filter-actions">
                    <button
                        className="admin-button admin-button-primary"
                        type="submit"
                    >
                        Apply filters
                    </button>
                    {hasFilters && (
                        <Link
                            className="admin-button admin-button-secondary"
                            href="/admin/users"
                        >
                            Clear
                        </Link>
                    )}
                </div>
            </form>

            {users.data.length === 0 ? (
                <EmptyState title="No matching accounts">
                    Change or clear the filters to review other operator accounts.
                </EmptyState>
            ) : (
                <>
                    <div className="admin-table-toolbar" role="status">
                        <strong>{users.total} operator accounts</strong>
                        <span>{hasFilters ? 'Matching current filters' : 'All local accounts'}</span>
                    </div>
                    <div className="admin-table-wrap">
                        <table className="admin-table admin-user-table">
                            <caption className="sr-only">
                                Operator accounts matching the current filters
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Operator</th>
                                    <th scope="col">Sign-in email</th>
                                    <th scope="col">Role</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {users.data.map((user) => (
                                    <tr className={user.is_current ? 'is-current-user' : undefined} key={user.id}>
                                        <th scope="row">
                                            {user.name}
                                            {user.is_current && (
                                                <small>Current account</small>
                                            )}
                                        </th>
                                        <td>{user.email}</td>
                                        <td>{user.role.label}</td>
                                        <td>
                                            <StatusText
                                                label={
                                                    user.is_active
                                                        ? 'Active'
                                                        : 'Inactive'
                                                }
                                                value={
                                                    user.is_active
                                                        ? 'active'
                                                        : 'inactive'
                                                }
                                            />
                                        </td>
                                        <td>
                                            <Link
                                                className="admin-row-action"
                                                href={`/admin/users/${user.id}/edit`}
                                            >
                                                Edit
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Pagination collection={users} itemLabel="accounts" />
                </>
            )}
        </AdminLayout>
    );
}
