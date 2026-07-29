export function FieldError({ id, message }) {
    if (!message) {
        return null;
    }

    return (
        <p className="admin-field-error" id={id} role="alert">
            {message}
        </p>
    );
}

export function ValidationSummary({ errors }) {
    const entries = Object.entries(errors ?? {});

    if (entries.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="validation-summary-title"
            className="admin-validation-summary"
            role="alert"
            tabIndex="-1"
        >
            <h2 id="validation-summary-title">Review the highlighted fields</h2>
            <ul>
                {entries.map(([field, message]) => (
                    <li key={field}>
                        <a href={`#${field}`}>{message}</a>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export function RequiredNote() {
    return (
        <p className="admin-required-note">
            Fields marked <span aria-hidden="true">*</span> are required.
        </p>
    );
}
