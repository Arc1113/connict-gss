const countDefinitions = [
    { key: 'total_count', label: 'Rows inspected' },
    { key: 'valid_count', label: 'Ready' },
    { key: 'warning_count', label: 'With warnings' },
    { key: 'error_count', label: 'Blocked' },
];

export default function ImportCounts({ counts }) {
    return (
        <dl className="admin-import-counts">
            {countDefinitions.map(({ key, label }) => (
                <div className={`admin-import-count admin-import-count-${key}`} key={key}>
                    <dt>{label}</dt>
                    <dd>{counts[key]}</dd>
                </div>
            ))}
        </dl>
    );
}
