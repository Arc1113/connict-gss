import CsvReplacement from './CsvReplacement';
import MediaPackageReplacement from './MediaPackageReplacement';

export default function ImportSourceReplacements({ studentImport, workspace }) {
    return (
        <section aria-labelledby="replace-import-source-title">
            <div className="admin-section-heading">
                <div>
                    <p className="admin-section-kicker">Need to correct a file?</p>
                    <h2 id="replace-import-source-title">Replace either source</h2>
                </div>
            </div>
            <div className="admin-source-replacements">
                <CsvReplacement
                    studentImport={studentImport}
                    workspace={workspace}
                />
                <MediaPackageReplacement
                    studentImport={studentImport}
                    workspace={workspace}
                />
            </div>
        </section>
    );
}
