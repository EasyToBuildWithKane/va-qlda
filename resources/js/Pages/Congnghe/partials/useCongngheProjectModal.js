import { ref } from 'vue';

/** @type {import('vue').Ref<object|null>} */
export const activeCongngheProject = ref(null);

export function openCongngheProject(project) {
    if (!project?.id) {
        return;
    }
    activeCongngheProject.value = project;
}

export function closeCongngheProject() {
    activeCongngheProject.value = null;
}
