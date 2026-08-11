import { Link } from '@inertiajs/react';

const sections = [
    ['overview', 'Overview'], ['academic', 'Academic'], ['media', 'Media & QR'], ['announcement', 'Announcement'], ['history', 'History'],
];

export default function GraduateDetailNav({ current, student, workspace }) {
    const base = workspace ? `${workspace.urls.graduates}/${student.id}` : `/admin/students/${student.id}`;
    return <nav aria-label="Graduate detail sections" className="graduate-detail-nav">{sections.map(([key, label]) => <Link aria-current={current === key ? 'page' : undefined} className={current === key ? 'is-current' : ''} href={key === 'overview' ? base : `${base}?section=${key}`} key={key}>{label}</Link>)}</nav>;
}
