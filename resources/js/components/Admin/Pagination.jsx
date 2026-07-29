import { Link } from '@inertiajs/react';

function labelFor(link) {
    if (link.label.includes('Previous')) {
        return 'Previous';
    }

    if (link.label.includes('Next')) {
        return 'Next';
    }

    return link.label;
}

export default function Pagination({ collection, itemLabel = 'records' }) {
    if (!collection || collection.total === 0) {
        return null;
    }

    return (
        <div className="admin-pagination">
            <p>
                Showing {collection.from}–{collection.to} of {collection.total}{' '}
                {itemLabel}
            </p>
            {collection.last_page > 1 && (
                <nav aria-label="Pagination">
                    {collection.links.map((link, index) =>
                        link.url ? (
                            <Link
                                aria-current={link.active ? 'page' : undefined}
                                className={link.active ? 'is-current' : ''}
                                href={link.url}
                                key={`${link.label}-${index}`}
                                preserveScroll
                            >
                                {labelFor(link)}
                            </Link>
                        ) : (
                            <span
                                aria-disabled="true"
                                className="is-disabled"
                                key={`${link.label}-${index}`}
                            >
                                {labelFor(link)}
                            </span>
                        ),
                    )}
                </nav>
            )}
        </div>
    );
}
