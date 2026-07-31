import { Head, useForm } from '@inertiajs/react';
import BrandMark from '../../components/BrandMark';

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
            <Head title="Operator sign in">
                <meta
                    name="description"
                    content="Operator access to the local Graduation Stage System."
                />
            </Head>

            <a className="skip-link" href="#main-content">
                Skip to sign in
            </a>

            <main className="operator-login" id="main-content">
                <section className="operator-login-card" aria-labelledby="login-heading">
                    <header>
                        <BrandMark />
                        <div>
                            <strong>Graduation Stage System</strong>
                            <span>Local ceremony operations</span>
                        </div>
                    </header>

                    <div className="operator-login-intro">
                        <p className="foundation-eyebrow">Operator access</p>
                        <h1 id="login-heading">Sign in</h1>
                        <p>
                            Your assigned workspace opens immediately after
                            authentication.
                        </p>
                    </div>

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
                                onChange={(event) =>
                                    setData('email', event.target.value)
                                }
                                aria-invalid={errors.email ? 'true' : undefined}
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
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
                                <p
                                    id="password-error"
                                    className="form-error"
                                    role="alert"
                                >
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
                            <span>Keep me signed in on this assigned device</span>
                        </label>

                        <button
                            className="primary-action auth-submit"
                            type="submit"
                            disabled={processing}
                        >
                            {processing ? 'Signing in…' : 'Open assigned workspace'}
                        </button>
                    </form>

                    <footer>
                        <span className="status-marker status-marker-ready" aria-hidden="true" />
                        Local network system
                    </footer>
                </section>
            </main>
        </>
    );
}
