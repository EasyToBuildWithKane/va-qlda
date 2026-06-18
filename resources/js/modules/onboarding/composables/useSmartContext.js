import { computed } from 'vue';

/**
 * Smart Context Guide — maps server-computed existence flags to the single most
 * relevant "next step" nudge. Returns null when nothing actionable applies
 * (so the hint card stays hidden). Only the highest-priority hint shows.
 */
export function useSmartContext(context, role) {
    const hint = computed(() => {
        const c = context.value || {};
        const r = role.value;

        // Quản lý mới: chưa có gì → dẫn tạo dự án đầu tiên.
        if (!c.hasProject && (r === 'super_admin' || r === 'admin' || r === 'lead')) {
            return {
                key: 'create_project',
                icon: 'projects',
                title: 'Bắt đầu với dự án đầu tiên',
                desc: 'Hệ thống chưa có dự án nào. Hãy tạo dự án để bắt đầu lập kế hoạch.',
                action: { label: 'Tạo dự án', href: '/projects/create' },
            };
        }
        if (c.hasProject && !c.hasSprint && (r === 'super_admin' || r === 'admin' || r === 'lead')) {
            return {
                key: 'create_sprint',
                icon: 'sprint',
                title: 'Khởi tạo Sprint',
                desc: 'Đã có dự án nhưng chưa có Sprint. Tạo Sprint để chia nhỏ kế hoạch.',
                action: { label: 'Mở dự án', href: '/projects' },
            };
        }
        if (c.hasSprint && !c.hasTask) {
            return {
                key: 'create_task',
                icon: 'task',
                title: 'Phân công công việc',
                desc: 'Sprint đã sẵn sàng — hãy tạo và giao Task cho nhóm.',
                action: { label: 'Mở dự án', href: '/projects' },
            };
        }
        if (!c.hasTeamMembers && (r === 'super_admin' || r === 'admin' || r === 'lead')) {
            return {
                key: 'add_members',
                icon: 'members',
                title: 'Thêm thành viên',
                desc: 'Nhóm chưa có thành viên. Thêm người để phân công công việc.',
                action: { label: 'Quản lý thành viên', href: '/org-teams/members' },
            };
        }
        return null;
    });

    return { hint };
}
