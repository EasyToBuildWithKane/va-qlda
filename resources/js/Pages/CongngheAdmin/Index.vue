<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ToggleSwitch from '@/shared/ui/form/ToggleSwitch.vue';
import SectionEditor from './partials/SectionEditor.vue';

const props = defineProps({
    sections: { type: Array, default: () => [] },
    icons: { type: Array, default: () => [] },
    metricKeys: { type: Array, default: () => [] },
    tones: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({}) },
});

const canManage = computed(() => !!props.can?.manage);

const orderableSections = computed(
    () => props.sections.filter((s) => s.orderable).sort((a, b) => a.position - b.position),
);
const chromeSections = computed(() => props.sections.filter((s) => !s.orderable));

const selectedKey = ref(orderableSections.value[0]?.key ?? props.sections[0]?.key ?? null);
const selected = computed(() => props.sections.find((s) => s.key === selectedKey.value) ?? null);

function select(key) {
    selectedKey.value = key;
}

function toggleVisible(section) {
    if (!canManage.value) return;
    router.put(
        `/congnghe/quan-tri/sections/${section.key}`,
        { content: section.data, is_visible: !section.is_visible },
        { preserveScroll: true },
    );
}

function move(index, dir) {
    if (!canManage.value) return;
    const keys = orderableSections.value.map((s) => s.key);
    const j = index + dir;
    if (j < 0 || j >= keys.length) return;
    [keys[index], keys[j]] = [keys[j], keys[index]];
    router.put('/congnghe/quan-tri/order', { order: keys }, { preserveScroll: true });
}
</script>

<template>
  <Head title="Quản trị trang Công Nghệ" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Quản trị trang Công Nghệ"
        subtitle="Chỉnh nội dung, bật/tắt và sắp xếp các mục của trang /congnghe"
        icon="rocket"
      >
        <Link
          href="/congnghe"
          class="btn-ghost border border-slate-200 text-sm"
          target="_blank"
        >
          <AppIcon
            name="external-link"
            :size="15"
          />
          Xem trang
        </Link>
      </PageHeader>
    </template>

    <div class="grid gap-5 lg:grid-cols-[300px_minmax(0,1fr)]">
      <!-- Cột trái: danh sách mục -->
      <aside class="flex flex-col gap-5">
        <section class="card p-3">
          <p class="px-1.5 pb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            Bố cục trang
          </p>
          <ul class="space-y-1">
            <li
              v-for="(section, index) in orderableSections"
              :key="section.key"
            >
              <div
                class="flex items-center gap-1.5 rounded-lg px-1.5 py-1 transition-colors"
                :class="selectedKey === section.key ? 'bg-brand/5 ring-1 ring-brand/15' : 'hover:bg-slate-50'"
              >
                <div class="flex shrink-0 flex-col">
                  <button
                    type="button"
                    class="grid h-4 w-5 place-items-center text-slate-300 hover:text-slate-600 disabled:opacity-30"
                    :disabled="!canManage || index === 0"
                    title="Lên"
                    @click="move(index, -1)"
                  >
                    <svg
                      width="13"
                      height="13"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2.5"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    ><path d="m18 15-6-6-6 6" /></svg>
                  </button>
                  <button
                    type="button"
                    class="grid h-4 w-5 place-items-center text-slate-300 hover:text-slate-600 disabled:opacity-30"
                    :disabled="!canManage || index === orderableSections.length - 1"
                    title="Xuống"
                    @click="move(index, 1)"
                  >
                    <svg
                      width="13"
                      height="13"
                      viewBox="0 0 24 24"
                      fill="none"
                      stroke="currentColor"
                      stroke-width="2.5"
                      stroke-linecap="round"
                      stroke-linejoin="round"
                    ><path d="m6 9 6 6 6-6" /></svg>
                  </button>
                </div>

                <button
                  type="button"
                  class="flex min-w-0 flex-1 items-center gap-2 py-1 text-left"
                  @click="select(section.key)"
                >
                  <AppIcon
                    :name="section.icon"
                    :size="16"
                    class="shrink-0"
                    :class="selectedKey === section.key ? 'text-brand' : 'text-slate-400'"
                  />
                  <span
                    class="min-w-0 flex-1 truncate text-[13px] font-medium"
                    :class="[
                      selectedKey === section.key ? 'text-brand' : 'text-slate-700',
                      section.is_visible ? '' : 'line-through opacity-50',
                    ]"
                  >{{ section.label }}</span>
                  <span
                    v-if="section.is_overridden"
                    class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"
                    title="Đã tuỳ chỉnh"
                  />
                </button>

                <ToggleSwitch
                  :model-value="section.is_visible"
                  :disabled="!canManage"
                  class="shrink-0 scale-90"
                  @update:model-value="toggleVisible(section)"
                />
              </div>
            </li>
          </ul>
        </section>

        <section class="card p-3">
          <p class="px-1.5 pb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">
            Khu vực cố định
          </p>
          <ul class="space-y-1">
            <li
              v-for="section in chromeSections"
              :key="section.key"
            >
              <button
                type="button"
                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left transition-colors"
                :class="selectedKey === section.key ? 'bg-brand/5 text-brand ring-1 ring-brand/15' : 'text-slate-700 hover:bg-slate-50'"
                @click="select(section.key)"
              >
                <AppIcon
                  :name="section.icon"
                  :size="16"
                  class="shrink-0"
                  :class="selectedKey === section.key ? 'text-brand' : 'text-slate-400'"
                />
                <span class="min-w-0 flex-1 truncate text-[13px] font-medium">{{ section.label }}</span>
                <span
                  v-if="section.is_overridden"
                  class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"
                  title="Đã tuỳ chỉnh"
                />
              </button>
            </li>
          </ul>
        </section>
      </aside>

      <!-- Cột phải: trình soạn thảo -->
      <section class="card min-w-0 p-5 md:p-6">
        <SectionEditor
          v-if="selected"
          :key="selected.key"
          :section="selected"
          :icons="icons"
          :metric-keys="metricKeys"
          :tones="tones"
          :can-manage="canManage"
        />
        <p
          v-else
          class="py-12 text-center text-sm text-slate-400"
        >
          Chọn một mục để chỉnh sửa.
        </p>
      </section>
    </div>
  </AppLayout>
</template>
