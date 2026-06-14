<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import CoachingSessionRowActions from '@/modules/coaching/components/CoachingSessionRowActions.vue';
import {
    sessionStatusColor,
    displaySessionDate,
    displaySessionHours,
    displaySessionTimeRange,
    displaySessionTopic,
    displayMaterialsCount,
    displayAssignmentsCount,
} from '@/composables/coachingSessionDisplay';

const props = defineProps({
    groups: { type: Array, required: true },
    statusOptions: { type: Array, default: () => [] },
    statusUpdatingIds: { type: Set, required: true },
    isGroupExpanded: { type: Function, required: true },
    isColVisible: { type: Function, required: true },
    visibleColumnCount: { type: Number, required: true },
});

const emit = defineEmits(['toggle-group', 'update-status', 'detail', 'delete']);

const tableColspan = computed(() => 2 + props.visibleColumnCount);
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[48rem] text-left text-sm">
      <thead>
        <tr class="border-b border-slate-100 bg-slate-50/50 text-xs font-medium text-slate-500">
          <th class="w-10 px-3 py-3" />
          <th class="w-12 px-3 py-3">
            #
          </th>
          <th class="min-w-[10rem] px-3 py-3">
            Tên buổi
          </th>
          <th
            v-if="isColVisible('course')"
            class="px-3 py-3"
          >
            Khóa học
          </th>
          <th
            v-if="isColVisible('date')"
            class="px-3 py-3"
          >
            Ngày
          </th>
          <th
            v-if="isColVisible('time')"
            class="px-3 py-3"
          >
            Giờ học
          </th>
          <th
            v-if="isColVisible('hours')"
            class="px-3 py-3 text-right"
          >
            Tổng giờ
          </th>
          <th
            v-if="isColVisible('status')"
            class="px-3 py-3"
          >
            Trạng thái
          </th>
          <th
            v-if="isColVisible('topic')"
            class="px-3 py-3"
          >
            Chủ đề
          </th>
          <th
            v-if="isColVisible('materials')"
            class="px-3 py-3"
          >
            Tài liệu
          </th>
          <th
            v-if="isColVisible('assignments')"
            class="px-3 py-3"
          >
            Bài tập
          </th>
          <th class="w-12 px-3 py-3 text-center">
            <span class="sr-only">Thao tác</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <template
          v-for="group in groups"
          :key="`g-${group.key}`"
        >
          <tr
            class="cursor-pointer border-y border-slate-200 bg-slate-100/70 transition hover:bg-slate-100"
            @click="emit('toggle-group', group.key)"
          >
            <td class="px-3 py-2 text-center">
              <AppIcon
                name="chevron-down"
                :size="15"
                class="inline-block text-slate-500 transition-transform"
                :class="isGroupExpanded(group.key) ? '' : '-rotate-90'"
              />
            </td>
            <td
              :colspan="tableColspan"
              class="px-3 py-2"
            >
              <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                <span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-800">{{ group.label }}</span>
                <span class="text-[11px] text-slate-500">
                  {{ group.stats.completed }}/{{ group.stats.total }} HT · {{ displaySessionHours(group.stats.hours) }}
                </span>
                <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold tabular-nums text-slate-600 ring-1 ring-slate-200">
                  {{ group.items.length }}
                </span>
              </div>
            </td>
            <td />
          </tr>
          <template v-if="isGroupExpanded(group.key)">
            <tr
              v-for="s in group.items"
              :key="s.id"
              class="border-b border-slate-50 transition hover:bg-brand/[0.02]"
            >
              <td />
              <td class="px-3 py-3 font-mono text-xs text-slate-400">
                {{ s.session_number }}
              </td>
              <td class="px-3 py-3 font-medium text-slate-800">
                {{ s.title }}
              </td>
              <td
                v-if="isColVisible('course')"
                class="px-3 py-3"
              >
                <Link
                  v-if="s.course"
                  :href="route('coaching.courses.show', { course: s.course.id })"
                  class="text-slate-700 hover:text-brand"
                >
                  <span class="font-mono text-xs text-slate-400">{{ s.course.code }}</span>
                  <span class="ml-1">{{ s.course.name }}</span>
                </Link>
                <span
                  v-else
                  class="text-slate-500"
                >Chưa gán khóa</span>
              </td>
              <td
                v-if="isColVisible('date')"
                class="px-3 py-3 text-slate-600"
              >
                {{ displaySessionDate(s.date) }}
              </td>
              <td
                v-if="isColVisible('time')"
                class="px-3 py-3 text-slate-600"
              >
                {{ displaySessionTimeRange(s) }}
              </td>
              <td
                v-if="isColVisible('hours')"
                class="px-3 py-3 text-right text-slate-600"
              >
                {{ displaySessionHours(s.total_hours) }}
              </td>
              <td
                v-if="isColVisible('status')"
                class="px-3 py-3"
              >
                <select
                  v-if="s.can?.update"
                  :value="s.status?.value"
                  class="input h-8 min-w-[7.5rem] max-w-full px-2 py-0 text-xs"
                  :disabled="statusUpdatingIds.has(s.id)"
                  @click.stop
                  @change="emit('update-status', s, $event.target.value)"
                >
                  <option
                    v-for="o in statusOptions"
                    :key="o.value"
                    :value="o.value"
                  >
                    {{ o.label }}
                  </option>
                </select>
                <Badge
                  v-else-if="s.status"
                  :label="s.status.label"
                  :color="sessionStatusColor(s.status.value)"
                />
              </td>
              <td
                v-if="isColVisible('topic')"
                class="max-w-[14rem] px-3 py-3 text-slate-600"
              >
                <span class="line-clamp-2">{{ displaySessionTopic(s.topic) }}</span>
              </td>
              <td
                v-if="isColVisible('materials')"
                class="px-3 py-3 text-slate-600"
              >
                {{ displayMaterialsCount(s) }}
              </td>
              <td
                v-if="isColVisible('assignments')"
                class="px-3 py-3 text-slate-600"
              >
                {{ displayAssignmentsCount(s) }}
              </td>
              <td class="px-3 py-3">
                <CoachingSessionRowActions
                  :session="s"
                  @detail="emit('detail', s)"
                  @delete="emit('delete', s)"
                />
              </td>
            </tr>
          </template>
        </template>
      </tbody>
    </table>
  </div>
</template>
