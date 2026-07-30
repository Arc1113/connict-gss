import { Link, usePage } from '@inertiajs/react';

function NavLink({ active, children, href }) {
    return (
        <Link
            className={`admin-nav-link${active ? ' admin-nav-link-active' : ''}`}
            href={href}
        >
            {children}
        </Link>
    );
}

export default function AdminLayout({ children }) {
    const { auth, flash } = usePage().props;
    const currentUrl = usePage().url;
    const abilities = auth.abilities;

    return (
        <div className="admin-app">
            <header className="admin-topbar">
                <div className="admin-topbar-inner">
                    <Link className="admin-brand" href="/admin">
                        <span>Graduation Stage System</span>
                        <small>Data administration</small>
                    </Link>

                    <nav className="admin-nav" aria-label="Administration">
                        <NavLink active={currentUrl === '/admin'} href="/admin">
                            Readiness
                        </NavLink>
                        {abilities.view_ceremonies && (
                            <NavLink
                                active={currentUrl.startsWith('/admin/ceremonies')}
                                href="/admin/ceremonies"
                            >
                                Ceremonies
                            </NavLink>
                        )}
                        {abilities.view_students && (
                            <NavLink
                                active={
                                    currentUrl.startsWith('/admin/students') &&
                                    !currentUrl.startsWith('/admin/students/import') &&
                                    !currentUrl.startsWith('/admin/students/qr-codes') &&
                                    !/\/admin\/students\/\d+\/qr-code/.test(
                                        currentUrl,
                                    )
                                }
                                href="/admin/students"
                            >
                                Graduates
                            </NavLink>
                        )}
                        {abilities.prepare_qr_codes && (
                            <NavLink
                                active={
                                    currentUrl.startsWith(
                                        '/admin/students/qr-codes',
                                    ) ||
                                    /\/admin\/students\/\d+\/qr-code/.test(
                                        currentUrl,
                                    )
                                }
                                href="/admin/students/qr-codes"
                            >
                                QR preparation
                            </NavLink>
                        )}
                        {abilities.import_students && (
                            <NavLink
                                active={currentUrl.startsWith(
                                    '/admin/students/import',
                                )}
                                href="/admin/students/import"
                            >
                                Import CSV
                            </NavLink>
                        )}
                        {abilities.view_audit_logs && (
                            <NavLink
                                active={currentUrl.startsWith('/admin/audit-logs')}
                                href="/admin/audit-logs"
                            >
                                Audit history
                            </NavLink>
                        )}
                        {abilities.view_operational_logs && (
                            <NavLink
                                active={
                                    currentUrl.startsWith('/admin/scan-logs') ||
                                    currentUrl.startsWith('/admin/stage-events')
                                }
                                href="/admin/scan-logs"
                            >
                                Operational logs
                            </NavLink>
                        )}
                        {abilities.view_ceremony_reports && (
                            <NavLink
                                active={currentUrl.startsWith('/admin/reports')}
                                href="/admin/reports/ceremony-summary"
                            >
                                Reports
                            </NavLink>
                        )}
                        {abilities.view_backups && (
                            <NavLink
                                active={currentUrl.startsWith('/admin/backups')}
                                href="/admin/backups"
                            >
                                Backups
                            </NavLink>
                        )}
                    </nav>

                    <div className="admin-account">
                        <span>
                            {auth.user.name}
                            <small>{auth.user.role.label}</small>
                        </span>
                        <Link href="/dashboard">Dashboard</Link>
                        <Link as="button" href="/logout" method="post">
                            Sign out
                        </Link>
                    </div>
                </div>
            </header>

            {flash?.success && (
                <div className="admin-flash admin-flash-success" role="status">
                    {flash.success}
                </div>
            )}
            {flash?.warning && (
                <div className="admin-flash admin-flash-warning" role="status">
                    {flash.warning}
                </div>
            )}

            <main className="admin-main">{children}</main>
        </div>
    );
}
