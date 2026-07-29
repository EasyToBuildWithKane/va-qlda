import { onMounted, ref, watch } from 'vue';

const STORAGE_PREFIX = 'va-workspace.profile.sections.';

export function useProfileSectionCollapse(sectionKey, defaultOpen = true) {
    const open = ref(defaultOpen);

    onMounted(() => {
        if (!sectionKey) {
            return;
        }
        try {
            const raw = localStorage.getItem(STORAGE_PREFIX + sectionKey);
            if (raw === '0' || raw === '1') {
                open.value = raw === '1';
            }
        } catch {
            /* ignore */
        }
    });

    watch(open, (value) => {
        if (!sectionKey) {
            return;
        }
        try {
            localStorage.setItem(STORAGE_PREFIX + sectionKey, value ? '1' : '0');
        } catch {
            /* ignore */
        }
    });

    function toggle() {
        open.value = !open.value;
    }

    return { open, toggle };
}
