import AdminLayout from '../../../components/Admin/AdminLayout';
import GuidedSetupLayout from '../../../components/Setup/GuidedSetupLayout';

export default function GuidedSetup(props) {
    return (
        <AdminLayout>
            <GuidedSetupLayout {...props} />
        </AdminLayout>
    );
}
