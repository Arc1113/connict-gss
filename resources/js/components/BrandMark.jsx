export default function BrandMark({ compact = false, inverse = false }) {
    return (
        <span
            aria-hidden="true"
            className={`brand-mark${compact ? ' brand-mark-compact' : ''}${inverse ? ' brand-mark-inverse' : ''}`}
        >
            <svg viewBox="0 0 48 48" role="img">
                <path d="M7 14.5 24 5l17 9.5-17 9.6L7 14.5Z" />
                <path d="M12 20.5v10.8L24 38l12-6.7V20.5" />
                <path d="M41 15v15" />
                <circle cx="41" cy="33" r="2.2" />
            </svg>
        </span>
    );
}
