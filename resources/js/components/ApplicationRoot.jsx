import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import PwaStatus from './PwaStatus';

export default function ApplicationRoot({ App, props }) {
    const [pageComponent, setPageComponent] = useState(props.initialPage.component);

    useEffect(() => {
        const removeNavigateListener = router.on('navigate', (event) => {
            setPageComponent(event.detail.page.component);
        });

        return () => {
            removeNavigateListener();
        };
    }, []);

    return (
        <>
            <App {...props} />
            <PwaStatus pageComponent={pageComponent} />
        </>
    );
}
