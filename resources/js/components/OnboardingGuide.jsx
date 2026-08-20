import { Link, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { requestJson } from '../lib/http';
import AppIcon from './AppIcon';
import { ONBOARDING_TOURS } from './onboardingTours';

const ACTIVE_TOUR_KEY = 'gss-active-onboarding-tour';

function storedActive(key) {
    try {
        return window.sessionStorage.getItem(ACTIVE_TOUR_KEY) === key;
    } catch {
        return false;
    }
}

export default function OnboardingGuide({ onboarding, onShowShortcuts, standalone = false }) {
    const steps = useMemo(() => ONBOARDING_TOURS[onboarding?.role] ?? [], [onboarding?.role]);
    const [offered, setOffered] = useState(Boolean(onboarding?.auto_offer));
    const [active, setActive] = useState(() => onboarding ? storedActive(onboarding.key) : false);
    const [stepIndex, setStepIndex] = useState(onboarding?.current_step ?? 0);
    const [position, setPosition] = useState(null);
    const [helpOpen, setHelpOpen] = useState(false);
    const [progressStatus, setProgressStatus] = useState(onboarding?.status ?? null);
    const dialogRef = useRef(null);
    const previousFocusRef = useRef(null);

    const persist = useCallback(async (status, currentStep = stepIndex) => {
        if (!onboarding) return;
        await requestJson(`/onboarding/${onboarding.key}`, {
            method: 'PUT',
            body: JSON.stringify({ status, current_step: currentStep }),
        });
    }, [onboarding, stepIndex]);

    const rememberActive = useCallback((value) => {
        try {
            if (value) window.sessionStorage.setItem(ACTIVE_TOUR_KEY, onboarding.key);
            else window.sessionStorage.removeItem(ACTIVE_TOUR_KEY);
        } catch {
            // Session persistence is optional when browser storage is unavailable.
        }
    }, [onboarding]);

    async function start(reset = false) {
        const savedStep = progressStatus === 'in_progress' ? stepIndex : onboarding?.current_step ?? 0;
        const next = reset ? 0 : Math.min(savedStep, Math.max(steps.length - 1, 0));
        if (reset) await requestJson(`/onboarding/${onboarding.key}/reset`, { method: 'POST' });
        setStepIndex(next);
        setOffered(false);
        setHelpOpen(false);
        setActive(true);
        setProgressStatus('in_progress');
        rememberActive(true);
        await persist('in_progress', next);
    }

    const stop = useCallback(async (status) => {
        setActive(false);
        setOffered(false);
        setPosition(null);
        setProgressStatus(status);
        rememberActive(false);
        await persist(status, stepIndex);
        previousFocusRef.current?.focus?.();
    }, [persist, rememberActive, stepIndex]);

    const move = useCallback(async (next) => {
        if (next >= steps.length) {
            await stop('completed');
            return;
        }
        setStepIndex(next);
        setProgressStatus('in_progress');
        await persist('in_progress', next);
    }, [persist, steps.length, stop]);

    useEffect(() => {
        if (!active || !steps[stepIndex]) return undefined;
        const step = steps[stepIndex];
        const expectedPath = step.href.split('?')[0].split('#')[0];

        if (window.location.pathname !== expectedPath) {
            router.visit(step.href, { preserveState: true });
            return undefined;
        }

        const target = document.querySelector(`[data-tour="${step.target}"]`);
        if (!target || target.getClientRects().length === 0 || window.getComputedStyle(target).visibility === 'hidden') {
            const frame = window.requestAnimationFrame(() => void move(stepIndex + 1));
            return () => window.cancelAnimationFrame(frame);
        }

        previousFocusRef.current ??= document.activeElement;
        target.classList.add('tour-target-active');
        target.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });

        const updatePosition = () => {
            const rect = target.getBoundingClientRect();
            const width = Math.min(360, window.innerWidth - 32);
            const left = Math.max(16, Math.min(rect.left, window.innerWidth - width - 16));
            const below = rect.bottom + 12;
            const top = below + 220 < window.innerHeight ? below : Math.max(16, rect.top - 220);
            setPosition({ left, top, width });
        };
        updatePosition();
        window.addEventListener('resize', updatePosition);
        window.addEventListener('scroll', updatePosition, true);

        return () => {
            target.classList.remove('tour-target-active');
            window.removeEventListener('resize', updatePosition);
            window.removeEventListener('scroll', updatePosition, true);
        };
    }, [active, move, stepIndex, steps]);

    useEffect(() => {
        if (!active || !position) return undefined;

        const frame = window.requestAnimationFrame(() => dialogRef.current?.focus());
        return () => window.cancelAnimationFrame(frame);
    }, [active, position, stepIndex]);

    useEffect(() => {
        if (!active) return undefined;
        const handleKey = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                void stop('in_progress');
                return;
            }
            if (event.key !== 'Tab' || !dialogRef.current) return;
            const controls = [...dialogRef.current.querySelectorAll('button:not(:disabled), [href]')];
            if (!controls.length) return;
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && (document.activeElement === first || document.activeElement === dialogRef.current)) {
                event.preventDefault(); last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault(); first.focus();
            }
        };
        window.addEventListener('keydown', handleKey);
        return () => window.removeEventListener('keydown', handleKey);
    }, [active, stop]);

    if (!onboarding) return null;
    const step = steps[stepIndex];

    return <>
        <div className={`app-help-menu${standalone ? ' app-help-menu-standalone' : ''}`}>
            <button aria-expanded={helpOpen} data-tour="help-menu" onClick={() => setHelpOpen((current) => !current)} title="Help and guidance" type="button">
                <AppIcon name="help" /><span>Help and guidance</span><kbd>?</kbd>
            </button>
            {helpOpen && <div className="app-help-popover">
                <strong>Help and guidance</strong>
                <button onClick={() => void start(onboarding.status === 'completed' || onboarding.status === 'skipped')} type="button">
                    {progressStatus === 'in_progress' ? 'Resume role guide' : 'Start role guide'}
                </button>
                {onShowShortcuts && <button onClick={onShowShortcuts} type="button">Keyboard shortcuts</button>}
            </div>}
        </div>

        {offered && <aside className="onboarding-welcome" aria-labelledby="onboarding-welcome-title">
            <button aria-label="Dismiss getting-started panel" className="onboarding-close" onClick={() => void stop('skipped')} type="button"><AppIcon name="close" /></button>
            <p>First-time guide</p>
            <h2 id="onboarding-welcome-title">{onboarding.title}</h2>
            <ul>{onboarding.checklist.map((item) => <li key={item.key} data-complete={item.complete ? 'true' : 'false'}><span aria-hidden="true">{item.complete ? '✓' : '○'}</span><div><Link href={item.href}>{item.label}</Link>{item.training && <small>Rehearsal only</small>}</div></li>)}</ul>
            <div><button className="admin-button admin-button-primary" onClick={() => void start()} type="button">Show me around</button><button className="admin-button admin-button-text" onClick={() => void stop('skipped')} type="button">Skip guide</button></div>
        </aside>}

        {active && step && position && <div className="tour-layer" role="presentation">
            <section aria-label={`${onboarding.title}: step ${stepIndex + 1} of ${steps.length}`} className="tour-coachmark" ref={dialogRef} style={position} tabIndex={-1}>
                <span>Step {stepIndex + 1} of {steps.length}</span>
                <h2>{step.title}</h2>
                <p>{step.body}</p>
                <div>
                    <button className="admin-button admin-button-text" disabled={stepIndex === 0} onClick={() => void move(stepIndex - 1)} type="button">Back</button>
                    <button className="admin-button admin-button-text" onClick={() => void stop('in_progress')} type="button">Pause</button>
                    <button className="admin-button admin-button-secondary" onClick={() => void stop('skipped')} type="button">Skip</button>
                    <button className="admin-button admin-button-primary" onClick={() => void move(stepIndex + 1)} type="button">{stepIndex === steps.length - 1 ? 'Finish' : 'Next'}</button>
                </div>
            </section>
        </div>}
    </>;
}
