import { useEffect, useId, useRef } from 'react';
import { createPortal } from 'react-dom';

export default function ConfirmDialog({
    busy = false,
    cancelLabel = 'Cancel',
    confirmLabel,
    description,
    onCancel,
    onConfirm,
    open,
    title,
    tone = 'default',
}) {
    const cancelRef = useRef(null);
    const openerRef = useRef(null);
    const busyRef = useRef(busy);
    const cancelHandlerRef = useRef(onCancel);
    const titleId = useId();
    const descriptionId = useId();

    useEffect(() => { busyRef.current = busy; }, [busy]);
    useEffect(() => { cancelHandlerRef.current = onCancel; }, [onCancel]);

    useEffect(() => {
        if (!open) return undefined;

        openerRef.current = document.activeElement;
        const timer = window.setTimeout(() => cancelRef.current?.focus(), 0);
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        function onKeyDown(event) {
            if (event.key === 'Escape' && !busyRef.current) {
                event.preventDefault();
                cancelHandlerRef.current();
                return;
            }
            if (event.key !== 'Tab') return;

            const controls = [...document.querySelectorAll('.confirm-dialog button:not([disabled])')];
            if (controls.length === 0) return;
            const first = controls[0];
            const last = controls[controls.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        document.addEventListener('keydown', onKeyDown);
        return () => {
            window.clearTimeout(timer);
            document.body.style.overflow = previousOverflow;
            document.removeEventListener('keydown', onKeyDown);
            openerRef.current?.focus?.();
        };
    }, [open]);

    if (!open) return null;

    return createPortal(
        <div className="confirm-dialog-overlay" onMouseDown={(event) => {
            if (event.target === event.currentTarget && !busy) onCancel();
        }}>
            <section
                aria-describedby={description ? descriptionId : undefined}
                aria-labelledby={titleId}
                aria-modal="true"
                className={`confirm-dialog confirm-dialog-${tone}`}
                role={tone === 'danger' ? 'alertdialog' : 'dialog'}
            >
                <div className="confirm-dialog-content">
                    <h2 id={titleId}>{title}</h2>
                    {description && <p id={descriptionId}>{description}</p>}
                </div>
                <div className="confirm-dialog-actions">
                    <button className="admin-button admin-button-secondary" disabled={busy} onClick={onCancel} ref={cancelRef} type="button">
                        {cancelLabel}
                    </button>
                    <button className={`admin-button ${tone === 'danger' ? 'admin-button-danger' : 'admin-button-primary'}`} disabled={busy} onClick={onConfirm} type="button">
                        {busy ? 'Working…' : confirmLabel}
                    </button>
                </div>
            </section>
        </div>,
        document.body,
    );
}
