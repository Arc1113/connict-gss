import { router } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import AppIcon from './AppIcon';

export default function CommandPalette({ commands, mode, onClose, onModeChange }) {
    const [query, setQuery] = useState('');
    const [selectedIndex, setSelectedIndex] = useState(0);
    const inputRef = useRef(null);
    const filtered = useMemo(() => {
        const normalized = query.trim().toLowerCase();

        if (!normalized) {
            return commands;
        }

        return commands.filter((command) =>
            `${command.label} ${command.group}`.toLowerCase().includes(normalized),
        );
    }, [commands, query]);

    useEffect(() => {
        const frame = window.requestAnimationFrame(() => inputRef.current?.focus());
        return () => window.cancelAnimationFrame(frame);
    }, [mode]);

    const run = (command) => {
        onClose();

        if (command.external) {
            window.open(command.href, '_blank', 'noopener,noreferrer');
            return;
        }

        router.visit(command.href);
    };

    return (
        <div className="command-overlay" onMouseDown={onClose} role="presentation">
            <section
                aria-label={mode === 'shortcuts' ? 'Keyboard shortcuts' : 'Command palette'}
                aria-modal="true"
                className="command-dialog"
                onMouseDown={(event) => event.stopPropagation()}
                role="dialog"
            >
                <header className="command-header">
                    <AppIcon name={mode === 'shortcuts' ? 'help' : 'search'} />
                    {mode === 'commands' ? (
                        <input
                            aria-label="Search commands"
                            autoComplete="off"
                            onChange={(event) => {
                                setQuery(event.target.value);
                                setSelectedIndex(0);
                            }}
                            onKeyDown={(event) => {
                                if (event.key === 'ArrowDown') {
                                    event.preventDefault();
                                    if (filtered.length > 0) {
                                        setSelectedIndex((current) =>
                                            Math.min(current + 1, filtered.length - 1),
                                        );
                                    }
                                }

                                if (event.key === 'ArrowUp') {
                                    event.preventDefault();
                                    setSelectedIndex((current) => Math.max(current - 1, 0));
                                }

                                if (event.key === 'Enter' && filtered[selectedIndex]) {
                                    event.preventDefault();
                                    run(filtered[selectedIndex]);
                                }
                            }}
                            placeholder="Go to a page or operation…"
                            ref={inputRef}
                            type="search"
                            value={query}
                        />
                    ) : (
                        <strong>Keyboard shortcuts</strong>
                    )}
                    <button aria-label="Close" onClick={onClose} type="button">
                        <AppIcon name="close" />
                    </button>
                </header>

                {mode === 'commands' ? (
                    <div className="command-results" role="listbox">
                        {filtered.length === 0 ? (
                            <p>No matching command</p>
                        ) : (
                            filtered.map((command, index) => (
                                <button
                                    aria-selected={index === selectedIndex}
                                    className={index === selectedIndex ? 'is-selected' : ''}
                                    key={`${command.group}-${command.href}`}
                                    onClick={() => run(command)}
                                    onMouseEnter={() => setSelectedIndex(index)}
                                    role="option"
                                    type="button"
                                >
                                    <AppIcon name={command.icon} />
                                    <span>
                                        <strong>{command.label}</strong>
                                        <small>{command.group}</small>
                                    </span>
                                    {command.external ? (
                                        <AppIcon name="external" size={15} />
                                    ) : (
                                        <kbd>Enter</kbd>
                                    )}
                                </button>
                            ))
                        )}
                    </div>
                ) : (
                    <dl className="shortcut-list">
                        <div><dt><kbd>Ctrl</kbd><span>+</span><kbd>K</kbd></dt><dd>Open command palette</dd></div>
                        <div><dt><kbd>/</kbd></dt><dd>Focus search on the current page</dd></div>
                        <div><dt><kbd>?</kbd></dt><dd>Open this shortcut reference</dd></div>
                        <div><dt><kbd>Esc</kbd></dt><dd>Close a dialog, drawer, or palette</dd></div>
                        <div><dt><kbd>↑</kbd><kbd>↓</kbd></dt><dd>Move through command results</dd></div>
                        <div><dt><kbd>Enter</kbd></dt><dd>Open the selected safe command</dd></div>
                    </dl>
                )}

                <footer className="command-footer">
                    <button
                        className={mode === 'commands' ? 'is-active' : ''}
                        onClick={() => onModeChange('commands')}
                        type="button"
                    >
                        Commands
                    </button>
                    <button
                        className={mode === 'shortcuts' ? 'is-active' : ''}
                        onClick={() => onModeChange('shortcuts')}
                        type="button"
                    >
                        Shortcuts
                    </button>
                </footer>
            </section>
        </div>
    );
}
