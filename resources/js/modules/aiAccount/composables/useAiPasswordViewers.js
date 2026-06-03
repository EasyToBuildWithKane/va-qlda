import { ref } from 'vue';
import { httpGet, httpPost, httpDelete } from '@/shared/services/http';
import { useToast } from '@/shared/composables/useToast';

export function useAiPasswordViewers() {
    const toast = useToast();
    const loading = ref(false);
    const saving = ref(false);
    const viewers = ref([]);
    const candidates = ref([]);
    const selectedAccount = ref(null);

    async function load(aiAccountId) {
        if (!aiAccountId) {
            viewers.value = [];
            candidates.value = [];
            selectedAccount.value = null;
            return;
        }

        loading.value = true;
        try {
            const res = await httpGet(route('api.ai-accounts.password-viewers.index'), {
                params: { ai_account_id: aiAccountId },
            });
            const data = res.data ?? res;
            selectedAccount.value = data.ai_account ?? null;
            viewers.value = data.viewers ?? [];
            candidates.value = data.candidates ?? [];
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không tải được danh sách quyền xem mật khẩu.');
            viewers.value = [];
            candidates.value = [];
        } finally {
            loading.value = false;
        }
    }

    async function addViewer(aiAccountId, systemAccountId) {
        saving.value = true;
        try {
            const res = await httpPost(route('api.ai-accounts.password-viewers.store'), {
                ai_account_id: aiAccountId,
                system_account_id: systemAccountId,
            });
            toast.success(res.message ?? 'Đã thêm thành viên.');
            await load(aiAccountId);
            return res.data?.viewer;
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không thêm được thành viên.');
            throw e;
        } finally {
            saving.value = false;
        }
    }

    async function removeViewer(viewerId, name, aiAccountId) {
        saving.value = true;
        try {
            const res = await httpDelete(route('api.ai-accounts.password-viewers.destroy', {
                passwordViewer: viewerId,
            }));
            toast.success(res.message ?? `Đã thu hồi quyền của ${name}.`);
            if (aiAccountId) {
                await load(aiAccountId);
            }
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không thu hồi được quyền.');
            throw e;
        } finally {
            saving.value = false;
        }
    }

    return {
        loading,
        saving,
        viewers,
        candidates,
        selectedAccount,
        load,
        addViewer,
        removeViewer,
    };
}
