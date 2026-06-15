import { toIterableList } from '@/modules/people/composables/useOrgTeamPeople.js';

/**
 * @param {number|null} sectionId
 * @param {object[]} sections
 */
export function resolveSectionIndex(sectionId, sections) {
    if (sectionId == null) {
        return null;
    }
    const list = toIterableList(sections);
    const idx = list.findIndex((s) => s.id === sectionId);

    return idx >= 0 ? idx : null;
}

/**
 * @param {object|null} team
 * @param {import('@inertiajs/vue3').InertiaForm} form
 */
export function hydrateOrgTeamFormFromTeam(team, form) {
    if (!team) {
        return;
    }
    const sections = toIterableList(team.sections);
    form.name = team.name;
    form.parent_id = team.parent_id;
    form.leader_id = team.leader?.id ?? null;
    form.sort_order = team.sort_order ?? 0;
    form.is_active = team.is_active ?? true;
    form.sections = sections.map((s, i) => ({
        title: s.title,
        sort_order: s.sort_order ?? i,
    }));
    form.members = toIterableList(team.members).map((m, i) => ({
        employee_id: m.employee?.id ?? m.employee_id ?? null,
        section_index: resolveSectionIndex(m.section?.id ?? m.section_id ?? null, sections),
        branch: m.branch?.value ?? m.branch ?? null,
        sort_order: m.sort_order ?? i,
    }));
}

/**
 * @param {object} formData
 * @param {{ forceRoot?: boolean }} options
 */
export function buildOrgTeamSubmitPayload(formData, options = {}) {
    const sections = (formData.sections ?? [])
        .map((s, i) => ({
            title: (s.title || '').trim(),
            sort_order: s.sort_order ?? i,
        }))
        .filter((s) => s.title !== '');

    const members = (formData.members ?? [])
        .filter((m) => m.employee_id)
        .map((m, i) => {
            let sectionIndex = m.section_index;
            if (sectionIndex != null && sectionIndex !== '') {
                sectionIndex = Number(sectionIndex);
                if (sectionIndex >= sections.length) {
                    sectionIndex = null;
                }
            } else {
                sectionIndex = null;
            }

            return {
                employee_id: m.employee_id,
                section_index: sectionIndex,
                branch: m.branch || null,
                sort_order: m.sort_order ?? i,
            };
        });

    return {
        ...formData,
        parent_id: options.forceRoot ? null : (formData.parent_id || null),
        sections,
        members,
    };
}

/** @param {number|null} sectionIndex */
export function emptyOrgTeamMemberRow(sectionIndex = null) {
    return {
        employee_id: null,
        section_index: sectionIndex,
        branch: null,
        sort_order: 0,
    };
}

/** Mẫu nhánh Phòng Công nghệ (chỉ tiêu đề — người dùng gán sau). */
export const TECH_DEPT_SECTION_PRESETS = [
    { title: 'Trưởng ban CNTT', sort_order: 0 },
    { title: 'Phó Phòng Công nghệ', sort_order: 1 },
];

export const TECH_DEPT_CHILD_NAME_SUGGESTIONS = ['Phần mềm', 'Phần cứng'];

/**
 * @param {import('@inertiajs/vue3').InertiaForm} form
 * @param {boolean} isRoot
 */
export function applyTechDeptSectionPresets(form, isRoot) {
    if (!isRoot || (form.sections?.length ?? 0) > 0) {
        return false;
    }
    form.sections = TECH_DEPT_SECTION_PRESETS.map((s) => ({ ...s }));

    return true;
}

/**
 * @param {object} node
 * @returns {Array<{ node: object, depth: number }>}
 */
export function flattenOrgTeamTree(node, depth = 0) {
    if (!node) {
        return [];
    }
    const rows = [{ node, depth }];
    for (const child of toIterableList(node.children)) {
        rows.push(...flattenOrgTeamTree(child, depth + 1));
    }

    return rows;
}
