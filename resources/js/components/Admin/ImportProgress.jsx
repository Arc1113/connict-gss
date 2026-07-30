const steps = [
    { number: 1, label: 'Upload CSV' },
    { number: 2, label: 'Map columns' },
    { number: 3, label: 'Review rows' },
    { number: 4, label: 'Import summary' },
];

export default function ImportProgress({ currentStep }) {
    return (
        <nav aria-label="Graduate import progress" className="admin-import-progress">
            <ol>
                {steps.map((step) => (
                    <li
                        aria-current={currentStep === step.number ? 'step' : undefined}
                        className={
                            step.number < currentStep
                                ? 'is-complete'
                                : step.number === currentStep
                                  ? 'is-current'
                                  : ''
                        }
                        key={step.number}
                    >
                        <span aria-hidden="true">{step.number}</span>
                        {step.label}
                    </li>
                ))}
            </ol>
        </nav>
    );
}
