import { Link, useForm } from '@inertiajs/react';
import { FieldError, RequiredNote, ValidationSummary } from './FormSupport';

const ROLE_GUIDANCE = {
    administrator: 'Full ceremony, graduate, account, audit, backup, and operational access.',
    data_encoder: 'Graduate preparation, imports, media, and QR preparation without live stage control.',
    scanner_operator: 'Arrival Scanner access for QR scans and controlled graduate lookup.',
    stage_operator: 'Stage Control and Audience Stage access for live ceremony operation.',
    technical_operator: 'Operational logs, reports, and technical evidence without graduate editing.',
};

function describedBy(field, error, hint = false) {
    return [hint ? `${field}-hint` : null, error ? `${field}-error` : null]
        .filter(Boolean)
        .join(' ') || undefined;
}

export default function UserForm({
    action,
    managedUser,
    roleOptions,
    submitLabel,
}) {
    const editing = Boolean(managedUser?.id);
    const { data, errors, processing, setData, post, put } = useForm({
        name: managedUser?.name ?? '',
        email: managedUser?.email ?? '',
        role: managedUser?.role?.value ?? 'scanner_operator',
        is_active: managedUser?.is_active ?? true,
        password: '',
        password_confirmation: '',
    });

    function submit(event) {
        event.preventDefault();

        const roleChanged = editing && data.role !== managedUser.role.value;
        const statusChanged = editing && data.is_active !== managedUser.is_active;

        if (
            (roleChanged || statusChanged) &&
            !window.confirm(
                `Save access changes for ${data.name || 'this operator'}? The new role or account status applies at the next authorization check.`,
            )
        ) {
            return;
        }

        if (editing) {
            put(action, { preserveScroll: true });
            return;
        }

        post(action, { preserveScroll: true });
    }

    return (
        <form className="admin-form" onSubmit={submit}>
            <ValidationSummary errors={errors} />
            <RequiredNote />

            <fieldset className="admin-form-section">
                <legend>Account identity and access</legend>
                <div className="admin-form-grid">
                    <div className="admin-field">
                        <label htmlFor="name">
                            Operator name <span aria-hidden="true">*</span>
                        </label>
                        <input
                            aria-describedby={describedBy('name', errors.name)}
                            aria-invalid={Boolean(errors.name)}
                            autoFocus
                            autoComplete="name"
                            id="name"
                            maxLength="255"
                            onChange={(event) => setData('name', event.target.value)}
                            required
                            type="text"
                            value={data.name}
                        />
                        <FieldError id="name-error" message={errors.name} />
                    </div>

                    <div className="admin-field">
                        <label htmlFor="email">
                            Sign-in email <span aria-hidden="true">*</span>
                        </label>
                        <input
                            aria-describedby={describedBy('email', errors.email)}
                            aria-invalid={Boolean(errors.email)}
                            autoComplete="username"
                            id="email"
                            maxLength="255"
                            onChange={(event) => setData('email', event.target.value)}
                            required
                            type="email"
                            value={data.email}
                        />
                        <FieldError id="email-error" message={errors.email} />
                    </div>

                    <div className="admin-field">
                        <label htmlFor="role">
                            Role <span aria-hidden="true">*</span>
                        </label>
                        <select
                            aria-describedby={describedBy('role', errors.role, true)}
                            aria-invalid={Boolean(errors.role)}
                            id="role"
                            onChange={(event) => setData('role', event.target.value)}
                            required
                            value={data.role}
                        >
                            {roleOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                        <p className="admin-field-hint" id="role-hint">
                            {ROLE_GUIDANCE[data.role] ??
                                'Assign only the duties this operator performs.'}
                        </p>
                        <FieldError id="role-error" message={errors.role} />
                    </div>

                    <div className="admin-field">
                        <span className="admin-field-label">Account status</span>
                        <label className="admin-checkbox-row" htmlFor="is_active">
                            <input
                                aria-describedby={
                                    errors.is_active
                                        ? 'is_active-error'
                                        : undefined
                                }
                                aria-invalid={Boolean(errors.is_active)}
                                checked={data.is_active}
                                id="is_active"
                                onChange={(event) =>
                                    setData('is_active', event.target.checked)
                                }
                                type="checkbox"
                            />
                            <span>
                                Active — allow this account to sign in and use its
                                assigned role.
                            </span>
                        </label>
                        <FieldError
                            id="is_active-error"
                            message={errors.is_active}
                        />
                    </div>
                </div>

                {managedUser?.is_current && (
                    <p className="admin-form-warning">
                        This is the account currently in use. It cannot deactivate
                        or remove its own administrator role.
                    </p>
                )}
            </fieldset>

            <fieldset className="admin-form-section">
                <legend>{editing ? 'Replace password' : 'Initial password'}</legend>
                {editing && (
                    <p className="admin-form-note">
                        Leave both password fields blank to keep the current
                        password.
                    </p>
                )}
                <div className="admin-form-grid">
                    <div className="admin-field">
                        <label htmlFor="password">
                            Password {!editing && <span aria-hidden="true">*</span>}
                        </label>
                        <input
                            aria-describedby={describedBy(
                                'password',
                                errors.password,
                                true,
                            )}
                            aria-invalid={Boolean(errors.password)}
                            autoComplete="new-password"
                            id="password"
                            minLength="12"
                            onChange={(event) =>
                                setData('password', event.target.value)
                            }
                            required={!editing}
                            type="password"
                            value={data.password}
                        />
                        <p className="admin-field-hint" id="password-hint">
                            Use at least 12 characters. Share credentials through an
                            approved offline process.
                        </p>
                        <FieldError
                            id="password-error"
                            message={errors.password}
                        />
                    </div>

                    <div className="admin-field">
                        <label htmlFor="password_confirmation">
                            Confirm password{' '}
                            {!editing && <span aria-hidden="true">*</span>}
                        </label>
                        <input
                            aria-describedby={describedBy(
                                'password_confirmation',
                                errors.password_confirmation,
                            )}
                            aria-invalid={Boolean(errors.password_confirmation)}
                            autoComplete="new-password"
                            id="password_confirmation"
                            minLength="12"
                            onChange={(event) =>
                                setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            required={!editing}
                            type="password"
                            value={data.password_confirmation}
                        />
                        <FieldError
                            id="password_confirmation-error"
                            message={errors.password_confirmation}
                        />
                    </div>
                </div>
            </fieldset>

            <div className="admin-form-actions">
                <button
                    className="admin-button admin-button-primary"
                    disabled={processing}
                    type="submit"
                >
                    {processing ? 'Saving…' : submitLabel}
                </button>
                <Link
                    className="admin-button admin-button-secondary"
                    href="/admin/users"
                >
                    Cancel
                </Link>
            </div>
        </form>
    );
}
