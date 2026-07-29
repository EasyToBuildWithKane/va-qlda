import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const MAX_DRAFTS = 20;
const STORAGE_PREFIX = 'va-workspace.project-create.drafts';

/** Fields persisted in a local create-form draft. */
export const PROJECT_CREATE_DRAFT_FIELDS = [
    'name',
    'description',
    'color',
    'status',
    'type',
    'scope',
    'scope_regions',
    'scope_departments',
    'department_id',
    'start_date',
    'due_date',
    'manager_id',
    'is_active',
];

export function pickDraftData(formData) {
    return PROJECT_CREATE_DRAFT_FIELDS.reduce((acc, key) => {
        const val = formData[key];
        acc[key] = Array.isArray(val) ? [...val] : val;
        return acc;
    }, {});
}

export function hasDraftContent(data) {
    if (!data) return false;
    if (data.name?.trim() || data.description?.trim()) return true;
    if (data.start_date || data.due_date || data.manager_id) return true;
    if ((data.scope_regions?.length ?? 0) > 0 || (data.scope_departments?.length ?? 0) > 0) return true;
    return false;
}

export function draftTitle(draft) {
    return draft?.data?.name?.trim() || 'Bản nháp chưa đặt tên';
}

export function formatDraftSavedAt(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function useProjectCreateDraft() {
    const page = usePage();
    const userId = computed(() => page.props.auth?.user?.id ?? 'guest');
    const storageKey = computed(() => `${STORAGE_PREFIX}.${userId.value}`);

    const readAll = () => {
        try {
            const raw = localStorage.getItem(storageKey.value);
            const list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list : [];
        } catch {
            return [];
        }
    };

    const drafts = ref(readAll());
    const activeDraftId = ref(null);

    const persist = (list) => {
        const sorted = [...list].sort(
            (a, b) => new Date(b.savedAt).getTime() - new Date(a.savedAt).getTime(),
        );
        const trimmed = sorted.slice(0, MAX_DRAFTS);
        localStorage.setItem(storageKey.value, JSON.stringify(trimmed));
        drafts.value = trimmed;
    };

    const refresh = () => {
        drafts.value = readAll();
    };

    const save = (formData, activeTab = 0) => {
        const id = activeDraftId.value || `draft-${Date.now()}`;
        const entry = {
            id,
            savedAt: new Date().toISOString(),
            activeTab,
            data: pickDraftData(formData),
        };
        const list = readAll().filter((d) => d.id !== id);
        list.unshift(entry);
        persist(list);
        activeDraftId.value = id;
        return entry;
    };

    const remove = (id) => {
        persist(readAll().filter((d) => d.id !== id));
        if (activeDraftId.value === id) activeDraftId.value = null;
    };

    const get = (id) => readAll().find((d) => d.id === id) ?? null;

    return {
        drafts,
        activeDraftId,
        refresh,
        save,
        remove,
        get,
    };
}
