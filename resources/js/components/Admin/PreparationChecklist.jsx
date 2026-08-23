import { Link } from '@inertiajs/react';

export default function PreparationChecklist({ checklist = [], compact = false }) {
    if (checklist.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="preparation-checklist-title"
            className={`preparation-checklist${compact ? ' preparation-checklist-compact' : ''}`}
        >
            <header>
                <div>
                    <p>Preparation checklist</p>
                    <h2 id="preparation-checklist-title">Ready the ceremony in order</h2>
                </div>
                <span className="preparation-checklist-count">
                    {checklist.filter((item) => item.status === 'ready').length}/{checklist.length} ready
                </span>
            </header>
            <ol>
                {checklist.map((item) => (
                    <li className={`is-${item.status}`} key={item.key}>
                        <span className="preparation-checklist-marker" aria-hidden="true">
                            {item.status === 'ready' ? '✓' : '!'}
                        </span>
                        <span className="preparation-checklist-copy">
                            <strong>{item.label}</strong>
                            <small>{item.detail}</small>
                        </span>
                        <Link href={item.href}>
                            {item.status === 'ready' ? 'Review' : 'Resolve'}
                        </Link>
                    </li>
                ))}
            </ol>
        </section>
    );
}
