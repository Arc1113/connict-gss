export default function EmptyState({ title, children, action }) {
    return (
        <section className="admin-empty-state">
            <h2>{title}</h2>
            <p>{children}</p>
            {action && <div>{action}</div>}
        </section>
    );
}
