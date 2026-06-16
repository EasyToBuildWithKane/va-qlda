<script setup>
import { computed, watch, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Avatar from '@/shared/ui/Avatar.vue';
import {
    activeCongngheProject,
    closeCongngheProject,
} from './useCongngheProjectModal.js';
import CongngheProjectGallery from './CongngheProjectGallery.vue';
import RichContentBody from '@/shared/ui/RichContentBody.vue';
import { tone } from './tones.js';
import {
    CONGNGHE_PROJECT_DESC_EMPTY_CLASS,
    CONGNGHE_PROJECT_DESC_HTML_CLASS,
    CONGNGHE_PROJECT_DESC_PLAIN_CLASS,
} from './congngheProjectDescriptionStyles.js';

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

const accent = computed(() => project.value?.color || '#9A0036');
const statusTone = computed(() => tone(project.value?.statusColor));
const progress = computed(() => Math.max(0, Math.min(100, Number(project.value?.progress ?? 0))));
const imageCount = computed(() => (project.value?.images ?? []).filter((i) => i?.url).length);

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
        class="fixed inset-0 z-[120] flex items-end justify-center p-0 sm:items-center sm:p-4 lg:p-6"
      >
        <div
          class="absolute inset-0 bg-[#05060c]/85 backdrop-blur-md"
          @click="closeCongngheProject"
        />

        <div
          class="cn-modal-panel relative flex max-h-[94vh] w-full max-w-[78rem] flex-col overflow-hidden rounded-t-3xl border border-white/12 bg-[#0b0c14]/98 shadow-[0_40px_120px_-30px_rgba(0,0,0,0.9)] sm:rounded-3xl"
          :style="{ '--accent': accent }"
          role="dialog"
          aria-modal="true"
          :aria-label="`Chi tiết dự án ${project.name}`"
        >
          <!-- ── Header cố định: định danh + chỉ số ── -->
          <header class="relative shrink-0 overflow-hidden border-b border-white/10 px-6 pb-5 pt-7 sm:px-8">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(90%_140%_at_15%_-30%,color-mix(in_srgb,var(--accent)_38%,transparent),transparent_62%)]" />
            <div
              class="pointer-events-none absolute inset-x-0 top-0 h-px"
              :style="{ background: 'linear-gradient(90deg,transparent,color-mix(in srgb,var(--accent) 90%,white),transparent)' }"
            />
            <span
              class="pointer-events-none absolute inset-y-0 left-0 w-1"
              :style="{ background: 'linear-gradient(180deg,var(--accent),transparent)' }"
            />

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

            <div class="relative flex flex-wrap items-center gap-2">
              <span
                v-if="project.code"
                class="font-mono text-[11px] uppercase tracking-[0.2em] [color:color-mix(in_srgb,var(--accent)_70%,white)]"
              >
                {{ project.code }}
              </span>
              <span
                v-if="project.type_label"
                class="inline-flex items-center rounded-full border border-white/15 bg-white/5 px-2.5 py-0.5 font-mono text-[10px] uppercase tracking-wider text-white/60"
              >
                {{ project.type_label }}
              </span>
            </div>
            <h2 class="relative mt-2 max-w-[calc(100%-2.5rem)] font-display text-2xl font-bold leading-tight text-white sm:text-[1.7rem] lg:text-[2rem]">
              {{ project.name }}
            </h2>

            <!-- Dải chỉ số: trạng thái · tiến độ · phụ trách -->
            <div class="relative mt-4 flex flex-wrap items-center gap-x-5 gap-y-3">
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset"
                :class="statusTone.soft"
              >
                <span
                  class="h-1.5 w-1.5 rounded-full"
                  :class="statusTone.dot"
                />
                {{ project.status }}
              </span>

              <div class="flex min-w-[10rem] flex-1 items-center gap-2.5 sm:max-w-xs">
                <span class="font-mono text-[10px] uppercase tracking-wider text-white/40">Tiến độ</span>
                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-white/10">
                  <div
                    class="h-full rounded-full"
                    :style="{ width: `${progress}%`, background: 'linear-gradient(110deg,var(--accent),color-mix(in srgb,var(--accent) 55%,white))' }"
                  />
                </div>
                <span class="font-mono text-[12px] font-semibold tabular-nums text-white/85">{{ progress }}%</span>
              </div>

              <div
                v-if="project.manager"
                class="ml-auto flex min-w-0 items-center gap-2.5"
              >
                <Avatar
                  :name="project.manager.name"
                  :src="project.manager.avatar"
                  :size="34"
                />
                <span class="min-w-0">
                  <span class="block truncate text-[13px] font-medium text-white/85">{{ project.manager.name }}</span>
                  <span class="block truncate text-[11px] text-white/45">{{ project.manager.role_title || 'Phụ trách chính' }}</span>
                </span>
              </div>
            </div>
          </header>

          <!-- ── Thân: ảnh (trái) · mô tả richtext (phải) ── -->
          <div class="relative min-h-0 flex-1 overflow-y-auto [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden lg:grid lg:grid-cols-[1.05fr_0.95fr] lg:overflow-hidden">
            <!-- Cột hình ảnh -->
            <section class="min-w-0 border-white/10 px-6 pb-7 pt-6 sm:px-8 lg:min-h-0 lg:overflow-y-auto lg:border-r lg:pb-8 lg:[-ms-overflow-style:none] lg:[scrollbar-width:none] lg:[&::-webkit-scrollbar]:hidden">
              <div class="flex items-center justify-between">
                <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-white/40">
                  Hình ảnh tham chiếu
                </p>
                <span
                  v-if="imageCount"
                  class="font-mono text-[10px] tabular-nums text-white/35"
                >{{ imageCount }} ảnh</span>
              </div>
              <CongngheProjectGallery
                :key="project.id"
                :images="project.images"
                density="modal"
              />
            </section>

            <!-- Cột mô tả (richtext) -->
            <section class="flex min-w-0 flex-col px-6 pb-7 pt-6 sm:px-8 lg:min-h-0 lg:pb-8">
              <div class="flex shrink-0 items-center gap-2.5">
                <span
                  class="h-4 w-1 rounded-full"
                  :style="{ background: 'var(--accent)' }"
                />
                <p class="font-mono text-[10px] uppercase tracking-[0.18em] text-white/45">
                  Mô tả dự án
                </p>
              </div>
              <div class="mt-3 min-h-0 flex-1 lg:overflow-y-auto lg:pr-2 [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-white/15">
                <RichContentBody
                  :content="project.description"
                  empty-text="Chưa có mô tả cho dự án này."
                  :html-class="CONGNGHE_PROJECT_DESC_HTML_CLASS"
                  :plain-class="CONGNGHE_PROJECT_DESC_PLAIN_CLASS"
                  :empty-class="CONGNGHE_PROJECT_DESC_EMPTY_CLASS"
                />
              </div>
            </section>
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
