import axios from 'axios';
import { useToast } from '@/shared/composables/useToast';

export function useCredentialAccess(credentialId) {
    const toast = useToast();

    async function grant(payload) {
        const { data } = await axios.post(
            route('api.credentials.access-grants.store', { credential: credentialId }),
            payload,
        );
        toast.success(data.message || 'Đã cấp quyền.');
        return data.data;
    }

    async function revoke(grantId) {
        const { data } = await axios.delete(
            route('api.credentials.access-grants.destroy', {
                credential: credentialId,
                accessGrant: grantId,
            }),
        );
        toast.success(data.message || 'Đã thu hồi.');
    }

    async function requestAccess(payload) {
        const { data } = await axios.post(
            route('api.credentials.access-requests.store', { credential: credentialId }),
            payload,
        );
        toast.success(data.message || 'Đã gửi yêu cầu.');
    }

    return { grant, revoke, requestAccess };
}
