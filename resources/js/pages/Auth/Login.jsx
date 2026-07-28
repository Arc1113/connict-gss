import { Head, Link, useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event) => {
        event.preventDefault();

        post('/login', {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Operator sign in" />

            <main className="auth-page">
                <section className="auth-card" aria-labelledby="login-heading">
                    <Link className="back-link" href="/">
                        Graduation Stage System
                    </Link>

                    <p className="foundation-eyebrow">Authorized operators</p>
                    <h1 id="login-heading" className="auth-title">
                        Sign in
                    </h1>
                    <p className="auth-copy">
                        Use the account issued by your system administrator.
                    </p>

                    <form className="auth-form" onSubmit={submit}>
                        <div className="form-field">
                            <label htmlFor="email">Email address</label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                autoComplete="username"
                                autoFocus
                                required
                                onChange={(event) => setData('email', event.target.value)}
                                aria-invalid={errors.email ? 'true' : undefined}
                                aria-describedby={errors.email ? 'email-error' : undefined}
                            />
                            {errors.email && (
                                <p id="email-error" className="form-error" role="alert">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div className="form-field">
                            <label htmlFor="password">Password</label>
                            <input
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                autoComplete="current-password"
                                required
                                onChange={(event) =>
                                    setData('password', event.target.value)
                                }
                                aria-invalid={errors.password ? 'true' : undefined}
                                aria-describedby={
                                    errors.password ? 'password-error' : undefined
                                }
                            />
                            {errors.password && (
                                <p id="password-error" className="form-error" role="alert">
                                    {errors.password}
                                </p>
                            )}
                        </div>

                        <label className="checkbox-field">
                            <input
                                type="checkbox"
                                name="remember"
                                checked={data.remember}
                                onChange={(event) =>
                                    setData('remember', event.target.checked)
                                }
                            />
                            <span>Keep me signed in on this operator device</span>
                        </label>

                        <button
                            className="primary-action auth-submit"
                            type="submit"
                            disabled={processing}
                        >
                            {processing ? 'Signing in…' : 'Sign in'}
                        </button>
                    </form>
                </section>
            </main>
        </>
    );
}
