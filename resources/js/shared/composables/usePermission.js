import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const HIERARCHY = ['viewer', 'member', 'lead', 'admin'];

export function usePermission() {
    const page = usePage();
    const role = computed(() => page.props.auth?.user?.role ?? null);

    const isRole = (...roles) => roles.includes(role.value);

    const isAtLeast = (minRole) =>
        HIERARCHY.indexOf(role.value) >= HIERARCHY.indexOf(minRole);

    const can = (action, resource = null) => {
        if (role.value === 'admin') return true;
        if (resource?.can?.[action] !== undefined) return resource.can[action];

        const defaults = {
            view: isAtLeast('viewer'),
            create: isAtLeast('member'),
            update: isAtLeast('lead'),
            delete: isAtLeast('admin'),
            manage: isAtLeast('lead'),
            contribute: isAtLeast('member'),
        };

        return defaults[action] ?? false;
    };

    return { role, isRole, isAtLeast, can };
}
