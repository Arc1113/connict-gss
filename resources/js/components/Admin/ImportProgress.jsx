const steps = [
    { number: 1, label: 'Upload' },
    { number: 2, label: 'Map' },
    { number: 3, label: 'Review' },
    { number: 4, label: 'Done' },
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
