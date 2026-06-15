<script setup>
import { computed, watch, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Avatar from '@/shared/ui/Avatar.vue';
import { tone } from './tones.js';
import {
    activeCongngheProject,
    closeCongngheProject,
} from './useCongngheProjectModal.js';
import CongngheProjectGallery from './CongngheProjectGallery.vue';

const page = usePage();

const open = computed(() => activeCongngheProject.value != null);

const project = computed(() => {
    const id = activeCongngheProject.value?.id;
    if (!id) {
        return null;
    }
    for (const phase of page.props.phases ?? []) {
        const hit = (phase.items ?? []).find((p) => p.id === id);
        if (hit) {
            return hit;
        }
    }
    const fromProducts = (page.props.products ?? []).find((p) => p.id === id);
    return fromProducts ?? activeCongngheProject.value;
});

watch(open, (isOpen) => {
    if (typeof document === 'undefined') {
        return;
    }
    document.body.style.overflow = isOpen ? 'hidden' : '';
});

function onKey(e) {
    if (e.key === 'Escape') {
        closeCongngheProject();
    }
}

watch(open, (isOpen) => {
    if (typeof window === 'undefined') {
        return;
    }
    if (isOpen) {
        window.addEventListener('keydown', onKey);
    } else {
        window.removeEventListener('keydown', onKey);
    }
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
  <Teleport to="body">
    <Transition name="cn-modal">
      <div
        v-if="open && project"
        class="fixed inset-0 z-[120] flex items-end justify-center p-0 sm:items-center sm:p-4"
      >
        <div
          class="absolute inset-0 bg-[#05060c]/85 backdrop-blur-md"
          @click="closeCongngheProject"
        />

        <div
          class="cn-modal-panel relative flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-t-3xl border border-white/12 bg-[#0d0e16]/98 shadow-[0_40px_120px_-30px_rgba(0,0,0,0.9)] sm:rounded-3xl"
          role="dialog"
          aria-modal="true"
          :aria-label="`Chi tiết dự án ${project.name}`"
        >
          <div class="pointer-events-none absolute inset-x-0 top-0 h-36 bg-[radial-gradient(80%_120%_at_50%_-20%,rgba(255,77,141,0.35),transparent_70%)]" />

          <button
            type="button"
            class="absolute right-4 top-4 z-10 grid h-9 w-9 place-items-center rounded-full border border-white/10 bg-white/5 text-white/60 transition hover:border-white/25 hover:bg-white/10 hover:text-white"
            aria-label="Đóng"
            @click="closeCongngheProject"
          >
            <svg
              width="16"
              height="16"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
            ><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>

          <div class="relative overflow-y-auto px-6 pb-8 pt-8 sm:px-8">
            <div class="flex flex-wrap items-center gap-2 pr-10">
              <span
                v-if="project.type_label"
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
                :class="tone(project.type_color).soft"
              >
                {{ project.type_label }}
              </span>
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
                :class="tone(project.statusColor).soft"
              >
                <span
                  class="h-1.5 w-1.5 rounded-full"
                  :class="tone(project.statusColor).dot"
                />
                {{ project.status }}
              </span>
            </div>

            <p
              v-if="project.code"
              class="mt-3 font-mono text-[11px] uppercase tracking-[0.2em] text-white/40"
            >
              {{ project.code }}
            </p>
            <h2 class="mt-1 font-display text-2xl font-bold leading-tight text-white sm:text-3xl">
              {{ project.name }}
            </h2>

            <div class="mt-4">
              <div class="flex items-center justify-between font-mono text-[11px] text-white/55">
                <span>TIẾN ĐỘ</span>
                <span class="font-semibold text-white">{{ project.progress }}%</span>
              </div>
              <div class="mt-2 h-2 overflow-hidden rounded-full bg-white/10">
                <div
                  class="h-full rounded-full bg-[linear-gradient(110deg,#9A0036,#ff4d8d,#9A0036)]"
                  :style="{ width: `${project.progress}%` }"
                />
              </div>
            </div>

            <div class="mt-6">
              <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-white/40">
                Mô tả
              </p>
              <p class="mt-2 text-sm leading-relaxed text-white/65 sm:text-[15px]">
                {{ project.description || 'Chưa có mô tả cho dự án này.' }}
              </p>
            </div>

            <div
              v-if="project.manager"
              class="mt-6 flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] p-4"
            >
              <Avatar
                :name="project.manager.name"
                :src="project.manager.avatar"
                :size="44"
              />
              <div class="min-w-0">
                <p class="truncate font-semibold text-white">
                  {{ project.manager.name }}
                </p>
                <p class="truncate text-sm text-white/50">
                  {{ project.manager.role_title || 'Phụ trách chính' }}
                </p>
              </div>
            </div>

            <div class="mt-8">
              <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-white/40">
                Hình ảnh tham chiếu
              </p>
              <CongngheProjectGallery
                :key="project.id"
                :images="project.images"
                density="modal"
              />
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.cn-modal-enter-active,
.cn-modal-leave-active {
    transition: opacity 0.25s ease;
}

.cn-modal-enter-from,
.cn-modal-leave-to {
    opacity: 0;
}

.cn-modal-enter-active .cn-modal-panel,
.cn-modal-leave-active .cn-modal-panel {
    transition: transform 0.3s cubic-bezier(0.22, 1, 0.36, 1), opacity 0.3s ease;
}

.cn-modal-enter-from .cn-modal-panel,
.cn-modal-leave-to .cn-modal-panel {
    transform: translateY(16px) scale(0.96);
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .cn-modal-enter-active,
    .cn-modal-leave-active,
    .cn-modal-enter-active .cn-modal-panel,
    .cn-modal-leave-active .cn-modal-panel {
        transition: none;
    }
}
</style>
