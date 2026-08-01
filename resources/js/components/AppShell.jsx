import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { observeEchoConnection } from '../lib/echo';
import AppIcon from './AppIcon';
import { buildNavigation } from './appNavigation';
import BrandMark from './BrandMark';
import CommandPalette from './CommandPalette';
import SystemStatusBar from './SystemStatusBar';

function ceremonyCopy(ceremonyContext) {
    return {
        ready: {
            detail: ceremonyContext.active?.academic_year || 'Active ceremony',
            label: ceremonyContext.active?.name || 'Active ceremony',
            shortLabel: 'active',
            tone: 'ready',
        },
        none: {
            detail: 'Live operations blocked',
            label: 'No active ceremony',
            shortLabel: 'not active',
            tone: 'warning',
        },
        conflict: {
            detail: 'Correct ceremony statuses',
            label: 'Activation conflict',
            shortLabel: 'conflict',
            tone: 'danger',
        },
        unavailable: {
            detail: 'Check the local database',
            label: 'Ceremony unavailable',
            shortLabel: 'unavailable',
            tone: 'danger',
        },
    }[ceremonyContext.state];
}

export default function AppShell({ children }) {
    const { auth, ceremonyContext } = usePage().props;
    const currentUrl = usePage().url;
    const [collapsed, setCollapsed] = useState(() => {
        try {
            return window.localStorage.getItem('gss-sidebar-collapsed') === 'true';
        } catch {
            return false;
        }
    });
    const [mobileOpen, setMobileOpen] = useState(false);
    const [paletteOpen, setPaletteOpen] = useState(false);
    const [paletteMode, setPaletteMode] = useState('commands');
    const [online, setOnline] = useState(() => navigator.onLine);
    const [echoState, setEchoState] = useState('connecting');
    const navigation = useMemo(
        () => buildNavigation(auth.abilities, currentUrl),
        [auth.abilities, currentUrl],
    );
    const commands = useMemo(
        () =>
            navigation.flatMap((group) =>
                group.items.map((item) => ({ ...item, group: group.label })),
            ),
        [navigation],
    );
    const currentPage =
        currentUrl === '/admin'
            ? 'Ceremony readiness'
            : commands.find((command) => command.active)?.label ?? 'Workspace';
    const ceremony = ceremonyCopy(ceremonyContext);

    useEffect(() => {
        const handleOnline = () => setOnline(true);
        const handleOffline = () => setOnline(false);
        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);
        const stopObserving = observeEchoConnection(setEchoState);

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
            stopObserving();
        };
    }, []);

    useEffect(() => {
        const handleKeyboard = (event) => {
            const target = event.target;
            const isTyping =
                target instanceof HTMLInputElement ||
                target instanceof HTMLTextAreaElement ||
                target instanceof HTMLSelectElement ||
                target?.isContentEditable;

            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                setPaletteMode('commands');
                setPaletteOpen(true);
                return;
            }

            if ((event.ctrlKey || event.metaKey) && event.key === '/') {
                event.preventDefault();
                setPaletteMode('shortcuts');
                setPaletteOpen(true);
                return;
            }

            if (event.key === 'Escape') {
                setPaletteOpen(false);
                setMobileOpen(false);
                return;
            }

            if (!isTyping && event.key === '?') {
                event.preventDefault();
                setPaletteMode('shortcuts');
                setPaletteOpen(true);
                return;
            }

            if (!isTyping && event.key === '/') {
                const search = document.querySelector(
                    '[data-page-search], input[type="search"], input[name="search"]',
                );

                if (search) {
                    event.preventDefault();
                    search.focus();
                    search.select?.();
                }
            }

            if (!isTyping && event.altKey && !event.ctrlKey && !event.metaKey) {
                const destinations = {
                    1: '/dashboard',
                    2: auth.abilities.operate_scanner ? '/scanner' : null,
                    3: auth.abilities.operate_stage ? '/stage-control' : null,
                    4: auth.abilities.operate_stage ? '/stage-control#queue' : null,
                    5: auth.abilities.view_students ? '/admin/students' : null,
                };
                const destination = destinations[event.key];

                if (destination) {
                    event.preventDefault();
                    router.visit(destination);
                }
            }
        };

        window.addEventListener('keydown', handleKeyboard);
        return () => window.removeEventListener('keydown', handleKeyboard);
    }, [auth.abilities]);

    const toggleSidebar = () => {
        if (window.matchMedia('(max-width: 56rem)').matches) {
            setMobileOpen((current) => !current);
            return;
        }

        setCollapsed((current) => {
            const next = !current;
            try {
                window.localStorage.setItem('gss-sidebar-collapsed', String(next));
            } catch {
                // The preference is optional when storage is unavailable.
            }
            return next;
        });
    };

    const connection = !online
        ? { label: 'Network offline', shortLabel: 'disconnected', tone: 'danger' }
        : ['connected', 'reconnected'].includes(echoState)
          ? { label: 'Live connected', shortLabel: 'connected', tone: 'ready' }
          : echoState === 'connecting'
            ? { label: 'Connecting', shortLabel: 'connecting', tone: 'warning' }
            : { label: 'Live disconnected', shortLabel: 'disconnected', tone: 'danger' };

    return (
        <div
            className="app-shell"
            data-collapsed={collapsed ? 'true' : 'false'}
            data-mobile-open={mobileOpen ? 'true' : 'false'}
        >
            <a className="skip-link" href="#app-content">Skip to page content</a>
            <aside className="app-sidebar">
                <header className="app-sidebar-brand">
                    <Link href="/dashboard" title="Graduation Stage System">
                        <BrandMark compact />
                        <span>
                            <strong>Graduation</strong>
                            <small>Stage System</small>
                        </span>
                    </Link>
                    <button
                        aria-label={collapsed ? 'Expand navigation' : 'Collapse navigation'}
                        onClick={toggleSidebar}
                        type="button"
                    >
                        <AppIcon name="chevron" />
                    </button>
                </header>

                <nav className="app-sidebar-nav" aria-label="Primary navigation">
                    {navigation.map((group) => (
                        <section key={group.label}>
                            <h2>{group.label}</h2>
                            <ul>
                                {group.items.map((item) => (
                                    <li key={`${group.label}-${item.href}`}>
                                        <Link
                                            aria-current={item.active ? 'page' : undefined}
                                            className={item.active ? 'is-active' : ''}
                                            href={item.href}
                                            onClick={() => setMobileOpen(false)}
                                            rel={item.external ? 'noreferrer' : undefined}
                                            target={item.external ? '_blank' : undefined}
                                            title={collapsed ? item.label : undefined}
                                        >
                                            <AppIcon name={item.icon} />
                                            <span>{item.label}</span>
                                            {item.external && <AppIcon name="external" size={13} />}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ))}
                </nav>

                <footer className="app-sidebar-footer">
                    <button
                        onClick={() => {
                            setPaletteMode('shortcuts');
                            setPaletteOpen(true);
                        }}
                        title="Keyboard shortcuts"
                        type="button"
                    >
                        <AppIcon name="help" />
                        <span>Keyboard shortcuts</span>
                        <kbd>?</kbd>
                    </button>
                </footer>
            </aside>

            <button
                aria-label="Close navigation"
                className="app-sidebar-scrim"
                onClick={() => setMobileOpen(false)}
                type="button"
            />

            <div className="app-shell-body">
                <header className="app-toolbar">
                    <div className="app-toolbar-page">
                        <button aria-label="Toggle navigation" onClick={toggleSidebar} type="button">
                            <AppIcon name="menu" />
                        </button>
                        <span>
                            <strong>{currentPage}</strong>
                        </span>
                    </div>

                    <div className={`app-toolbar-ceremony app-status-${ceremony.tone}`}>
                        <span className="app-status-symbol" aria-hidden="true" />
                        <span>
                            <strong>{ceremony.label}</strong>
                            <small>{ceremony.detail}</small>
                        </span>
                    </div>

                    <div className="app-toolbar-actions">
                        <div className={`app-connection app-status-${connection.tone}`}>
                            <span className="app-status-symbol" aria-hidden="true" />
                            <span>{connection.label}</span>
                        </div>
                        <button
                            className="app-command-trigger"
                            onClick={() => {
                                setPaletteMode('commands');
                                setPaletteOpen(true);
                            }}
                            type="button"
                        >
                            <AppIcon name="search" />
                            <span>Command</span>
                            <kbd>Ctrl K</kbd>
                        </button>
                        <details className="app-user-menu">
                            <summary aria-label="User and session menu">
                                <span>{auth.user.name.slice(0, 1).toUpperCase()}</span>
                                <AppIcon name="chevron" size={14} />
                            </summary>
                            <div>
                                <header>
                                    <strong>{auth.user.name}</strong>
                                    <span>{auth.user.role.label}</span>
                                </header>
                                <Link href="/dashboard">Open overview</Link>
                                <Link as="button" href="/logout" method="post">Sign out</Link>
                            </div>
                        </details>
                    </div>
                </header>

                <main className="app-workspace" id="app-content">
                    {children}
                </main>
                <SystemStatusBar
                    ceremony={ceremony}
                    connection={connection}
                    online={online}
                />
            </div>

            {paletteOpen && (
                <CommandPalette
                    commands={commands}
                    mode={paletteMode}
                    onClose={() => setPaletteOpen(false)}
                    onModeChange={setPaletteMode}
                />
            )}
        </div>
    );
}
