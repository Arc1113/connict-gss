export default function GuidedSetupProgress({ steps, currentKey, skipped = [] }) {
    return (
        <nav aria-label="Guided setup progress" className="guided-setup-progress">
            <ol>
                {steps.filter((step) => step.key !== 'readiness').map((step, index) => (
                    <li className={`is-${step.status}${step.key === currentKey ? ' is-current' : ''}`} key={step.key}>
                        <span aria-hidden="true">{step.status === 'ready' ? '✓' : index + 1}</span>
                        <div>
                            <strong>{step.label}</strong>
                            {skipped.includes(step.key) && step.status !== 'ready' && <small>Skipped for now</small>}
                        </div>
                    </li>
                ))}
            </ol>
        </nav>
    );
}
