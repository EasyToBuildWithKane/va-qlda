import { ref } from 'vue';
import axios from 'axios';
import { useToast } from '@/shared/composables/useToast';

export function useCredentialPassword(credentialId, canViewRef) {
    const toast = useToast();
    const visible = ref(false);
    const password = ref('');
    const loading = ref(false);

    function allowed() {
        return typeof canViewRef === 'function' ? canViewRef() : canViewRef;
    }

    async function reveal(action = 'view') {
        if (!allowed()) {
            toast.error('Bạn không có quyền xem mật khẩu.');
            return;
        }
        loading.value = true;
        try {
            const { data } = await axios.get(
                route('api.credentials.show-password', { credential: credentialId }),
                { params: { action } },
            );
            password.value = data.data?.password ?? '';
            visible.value = true;
            if (action === 'copy' && password.value) {
                await navigator.clipboard.writeText(password.value);
                toast.success('Đã sao chép mật khẩu (đã ghi audit).');
            }
        } catch {
            toast.error('Không thể lấy mật khẩu.');
        } finally {
            loading.value = false;
        }
    }

    async function copy() {
        if (password.value) {
            await navigator.clipboard.writeText(password.value);
            toast.success('Đã sao chép.');
            return;
        }
        await reveal('copy');
    }

    function hide() {
        visible.value = false;
        password.value = '';
    }

    return { visible, password, loading, reveal, copy, hide };
}
