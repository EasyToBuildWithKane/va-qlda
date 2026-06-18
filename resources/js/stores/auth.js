import { defineStore } from 'pinia';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export const useAuthStore = defineStore('auth', () => {
    const page = usePage();

    const user = computed(() => page.props.auth?.user ?? null);
    const role = computed(() => user.value?.role ?? null);
    const employeeId = computed(() => user.value?.employee_id ?? null);
    const displayName = computed(() => user.value?.display_name ?? '');

    const isSuperAdmin = computed(() => role.value === 'super_admin');
    // Admin-tier = super_admin or admin (super is a superset of admin).
    const isAdmin = computed(() => role.value === 'admin' || role.value === 'super_admin');
    const isLead = computed(() => role.value === 'lead');
    const isMember = computed(() => role.value === 'member');
    const isViewer = computed(() => role.value === 'viewer');

    const isAtLeast = (minRole) => {
        const hierarchy = ['viewer', 'member', 'lead', 'admin', 'super_admin'];
        return hierarchy.indexOf(role.value) >= hierarchy.indexOf(minRole);
    };

    return {
        user,
        role,
        employeeId,
        displayName,
        isSuperAdmin,
        isAdmin,
        isLead,
        isMember,
        isViewer,
        isAtLeast,
    };
});
