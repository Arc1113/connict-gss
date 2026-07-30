export default function StatusText({ label, value }) {
    const semanticClass = ['active', 'eligible', 'ready', 'completed'].includes(value)
        ? 'admin-status-success'
        : ['ineligible', 'conflict', 'missing'].includes(value)
          ? 'admin-status-danger'
        : ['paused', 'pending', 'prepared', 'regenerated', 'warning'].includes(
                value,
            )
            ? 'admin-status-warning'
            : 'admin-status-neutral';

    return (
        <span className={`admin-status ${semanticClass}`}>
            <span aria-hidden="true" className="admin-status-marker" />
            {label}
        </span>
    );
}
