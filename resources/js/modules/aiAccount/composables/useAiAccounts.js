import { ref, computed } from 'vue';
import { httpGet, httpPost, httpPut, httpPatch, httpDelete, httpClient } from '@/shared/services/http';
import { useToast } from '@/shared/composables/useToast';

function toFormData(payload) {
    const fd = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
        if (value === undefined || value === null) return;
        if (key === 'proposal_documents' || key === 'payment_request_documents') {
            const files = Array.isArray(value) ? value : [];
            files.forEach((f) => {
                if (f instanceof File) fd.append(`${key}[]`, f);
            });
            return;
        }
        if (typeof value === 'boolean') {
            fd.append(key, value ? '1' : '0');
            return;
        }
        fd.append(key, String(value));
    });
    return fd;
}

export function useAiAccounts() {
    const toast = useToast();
    const loading = ref(false);
    const groups = ref([]);
    const banner = ref(null);
    const summaryCards = ref(null);
    const search = ref('');
    const expanded = ref({});
    const allGroupsExpanded = ref(true);

    function setAllExpanded(value) {
        allGroupsExpanded.value = value;
        for (const g of groups.value) {
            expanded.value[g.group] = value;
        }
    }

    async function fetchList() {
        loading.value = true;
        try {
            const params = {};
            const q = search.value.trim();
            if (q) params.search = q;
            const res = await httpGet(route('api.ai-accounts.index'), { params });
            const data = res.data ?? res;
            groups.value = data.groups ?? [];
            banner.value = data.banner ?? null;
            summaryCards.value = data.summary_cards ?? null;
            for (const g of groups.value) {
                if (expanded.value[g.group] === undefined) {
                    expanded.value[g.group] = allGroupsExpanded.value
                        || !!g.default_expanded;
                }
            }
            if (allGroupsExpanded.value) {
                setAllExpanded(true);
            }
        } catch (e) {
            toast.error(e.response?.data?.message ?? 'Không tải được danh sách tài khoản AI.');
        } finally {
            loading.value = false;
        }
    }

    async function createAccount(payload) {
        const hasFiles = (payload.proposal_documents?.length || 0) > 0
            || (payload.payment_request_documents?.length || 0) > 0;
        let res;
        if (hasFiles) {
            const { data } = await httpClient.post(
                route('api.ai-accounts.store'),
                toFormData(payload),
            );
            res = data;
        } else {
            res = await httpPost(route('api.ai-accounts.store'), payload);
        }
        toast.success(res.message ?? 'Đã lưu thành công.');
        await fetchList();
        return res.data?.account;
    }

    async function updateAccount(id, payload) {
        const hasFiles = (payload.proposal_documents?.length || 0) > 0
            || (payload.payment_request_documents?.length || 0) > 0;
        let res;
        if (hasFiles) {
            const fd = toFormData(payload);
            fd.append('_method', 'PUT');
            const { data } = await httpClient.post(
                route('api.ai-accounts.update.multipart', { aiAccount: id }),
                fd,
            );
            res = data;
        } else {
            res = await httpPut(route('api.ai-accounts.update', { aiAccount: id }), payload);
        }
        toast.success(res.message ?? 'Đã lưu thành công.');
        await fetchList();
        return res.data?.account;
    }

    async function updateAccountStatus(id, { status, expiry_date = null, sync_expiry_on_expire = true }) {
        const res = await httpPatch(route('api.ai-accounts.update-status', { aiAccount: id }), {
            status,
            expiry_date,
            sync_expiry_on_expire,
        });
        toast.success(res.message ?? 'Đã cập nhật trạng thái.');
        await fetchList();
        return res.data?.account;
    }

    async function deleteAccount(id, toolName) {
        const res = await httpDelete(route('api.ai-accounts.destroy', { aiAccount: id }));
        toast.success(res.message ?? `Đã xoá ${toolName}.`);
        await fetchList();
    }

    async function renewAccount(id, payload) {
        const res = await httpPost(route('api.ai-accounts.renew', { aiAccount: id }), payload);
        toast.success(res.message ?? 'Đã gia hạn tài khoản.');
        await fetchList();
        return res.data?.account;
    }

    async function triggerReminder() {
        const res = await httpPost(route('api.ai-accounts.trigger-reminder'));
        toast.success(res.message ?? 'Đã chạy nhắc nhở.');
    }

    function toggleGroup(groupKey) {
        expanded.value[groupKey] = !expanded.value[groupKey];
        allGroupsExpanded.value = groups.value.every((g) => expanded.value[g.group]);
    }

    function expandAllGroups() {
        setAllExpanded(true);
    }

    function collapseAllGroups() {
        setAllExpanded(false);
    }

    function toggleAllGroups() {
        if (allGroupsExpanded.value) collapseAllGroups();
        else expandAllGroups();
    }

    const totalAccountCount = computed(() =>
        groups.value.reduce((n, g) => n + (g.accounts?.length ?? 0), 0),
    );

    return {
        loading,
        groups,
        banner,
        summaryCards,
        search,
        expanded,
        allGroupsExpanded,
        totalAccountCount,
        fetchList,
        createAccount,
        updateAccount,
        updateAccountStatus,
        deleteAccount,
        renewAccount,
        triggerReminder,
        toggleGroup,
        expandAllGroups,
        collapseAllGroups,
        toggleAllGroups,
    };
}
