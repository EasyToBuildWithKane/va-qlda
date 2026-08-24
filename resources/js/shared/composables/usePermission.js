import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const HIERARCHY = ['viewer', 'member', 'team_leader', 'deputy_manager', 'manager', 'admin', 'super_admin'];

export function usePermission() {
    const page = usePage();
    const role = computed(() => page.props.auth?.user?.role ?? null);
    const isSuperAdmin = computed(() => page.props.auth?.user?.is_super_admin === true);
    const isAdminTier = computed(() => page.props.auth?.user?.is_admin_tier === true);
    const permissions = computed(() => page.props.auth?.user?.permissions ?? []);

    const isRole = (...roles) => roles.includes(role.value);

    const isAtLeast = (minRole) =>
        HIERARCHY.indexOf(role.value) >= HIERARCHY.indexOf(minRole);

    /**
     * RBAC permission check against the resolved matrix grants. Mirrors the
     * backend hierarchy: '*' → '{module}.*' → exact key.
     */
    const allows = (key) => {
        if (isSuperAdmin.value) return true;
        const list = permissions.value;
        if (list.includes('*') || list.includes(key)) return true;
        if (typeof key === 'string' && key.includes('.')) {
            const module = key.slice(0, key.indexOf('.'));
            if (list.includes(`${module}.*`)) return true;
        }
        return false;
    };

    /**
     * Backward-compatible gate. A dotted key ('module.action') routes through
     * the RBAC matrix; a short action keeps the legacy resource/heuristic path.
     */
    const can = (action, resource = null) => {
        if (isSuperAdmin.value) return true;
        if (typeof action === 'string' && action.includes('.')) return allows(action);
        if (role.value === 'admin') return true;
        if (resource?.can?.[action] !== undefined) return resource.can[action];

        const defaults = {
            view: isAtLeast('viewer'),
            create: isAtLeast('member'),
            update: isAtLeast('team_leader'),
            delete: isAtLeast('admin'),
            manage: isAtLeast('team_leader'),
            contribute: isAtLeast('member'),
        };

        return defaults[action] ?? false;
    };

    return { role, isRole, isAtLeast, isSuperAdmin, isAdminTier, permissions, allows, can };
}
