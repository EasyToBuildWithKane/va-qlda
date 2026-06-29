/** Virtual project row in `projects` JSON — not a real Project. */
export const ROUTINE_PROJECT_ID = -1;

export const ROUTINE_PROJECT_NAME = 'Công việc thường xuyên';

export function isRoutineProjectEntry(entry) {
    return entry?.id === ROUTINE_PROJECT_ID;
}
