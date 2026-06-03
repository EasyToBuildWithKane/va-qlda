import { ref } from 'vue';
import { httpGet, httpPost, httpDelete } from '@/shared/services/http';
import { useToast } from '@/shared/composables/useToast';

export function useAiPasswordViewers() {
    const toast = useToast();
    const loading = ref(false);
    const saving = ref(false);
    const viewers = ref([]);
    const candidates = ref([]);

    async function load() {
        loading.value = true;
        try {
            const res = await httpGet(route('api.ai-accounts.password-viewers.index'));
            const data = res.data ?? res;
            viewers.value = data.viewers ?? [];
            candidates.value = data.candidates ?? [];
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không tải được danh sách quyền xem mật khẩu.');
        } finally {
            loading.value = false;
        }
    }

    async function addViewer(systemAccountId) {
        saving.value = true;
        try {
            const res = await httpPost(route('api.ai-accounts.password-viewers.store'), {
                system_account_id: systemAccountId,
            });
            toast.success(res.message ?? 'Đã thêm thành viên.');
            await load();
            return res.data?.viewer;
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không thêm được thành viên.');
            throw e;
        } finally {
            saving.value = false;
        }
    }

    async function removeViewer(viewerId, name) {
        saving.value = true;
        try {
            const res = await httpDelete(route('api.ai-accounts.password-viewers.destroy', {
                passwordViewer: viewerId,
            }));
            toast.success(res.message ?? `Đã thu hồi quyền của ${name}.`);
            await load();
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
        load,
        addViewer,
        removeViewer,
    };
}
