import { usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import AppIcon from './AppIcon';
import { buildNavigation } from './appNavigation';
import CommandPalette from './CommandPalette';

export default function OperationCommandMenu() {
    const { auth, workingCeremonyContext } = usePage().props;
    const currentUrl = usePage().url;
    const [open, setOpen] = useState(false);
    const [mode, setMode] = useState('commands');
    const workingCeremony = workingCeremonyContext?.ceremony;
    const commands = useMemo(
        () =>
            buildNavigation(auth.abilities, currentUrl, { workingCeremony }).flatMap((group) =>
                group.items.map((item) => ({ ...item, group: group.label })),
            ),
        [auth.abilities, currentUrl, workingCeremony],
    );

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
                setMode('commands');
                setOpen(true);
                return;
            }

            if (event.key === 'Escape') {
                setOpen(false);
                return;
            }

            if (!isTyping && event.key === '?') {
                event.preventDefault();
                setMode('shortcuts');
                setOpen(true);
                return;
            }

            if (!isTyping && event.key === '/') {
                const search = document.querySelector(
                    '#scan-token, [data-page-search], input[type="search"]',
                );

                if (search) {
                    event.preventDefault();
                    search.focus();
                    search.select?.();
                }
            }
        };

        window.addEventListener('keydown', handleKeyboard);
        return () => window.removeEventListener('keydown', handleKeyboard);
    }, []);

    return (
        <>
            <button
                className="operation-command-trigger"
                onClick={() => {
                    setMode('commands');
                    setOpen(true);
                }}
                type="button"
            >
                <AppIcon name="search" size={16} />
                <span>Command</span>
                <kbd>⌘K</kbd>
            </button>
            {open && (
                <CommandPalette
                    commands={commands}
                    mode={mode}
                    onClose={() => setOpen(false)}
                    onModeChange={setMode}
                />
            )}
        </>
    );
}
