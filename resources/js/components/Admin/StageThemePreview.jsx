import { DEFAULT_STAGE_THEME } from '../../lib/stageTheme';

export default function StageThemePreview({ backgroundUrl, logoUrl, theme }) {
    const current = { ...DEFAULT_STAGE_THEME, ...theme };

    return (
        <div
            className="stage-theme-preview"
            data-layout={current.layout}
            data-preset={current.preset}
            style={{
                '--preview-accent': current.accent_color,
                '--preview-background': current.background_color,
                '--preview-surface': current.surface_color,
                '--preview-text': current.text_color,
                backgroundImage: backgroundUrl ? `url(${backgroundUrl})` : undefined,
            }}
        >
            <div className="stage-theme-preview-shade" />
            {logoUrl ? (
                <img alt="Ceremony logo preview" src={logoUrl} />
            ) : (
                <span className="stage-theme-preview-logo">GSS</span>
            )}
            <div className="stage-theme-preview-content">
                <small>Sample Graduation Ceremony</small>
                <strong>Alexandra Reyes</strong>
                <span>Bachelor of Science in Computer Science</span>
                <em>Magna Cum Laude</em>
            </div>
            {current.show_photo && <div className="stage-theme-preview-photo">Portrait</div>}
        </div>
    );
}
