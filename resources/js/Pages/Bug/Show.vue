<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';
import BugFormModal from '@/modules/project/components/BugFormModal.vue';
import { date } from '@/composables/useFormat';

const props = defineProps({
    bug: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

const modal = ref(false);

const changeStatus = (e) => router.put(`/bugs/${props.bug.id}`, { status: e.target.value }, { preserveScroll: true });
</script>

<template>
  <Head :title="bug.code + ' — ' + bug.title" />
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-2">
        <Link
          href="/bugs"
          class="grid h-8 w-8 place-items-center rounded-btn text-slate-400 hover:bg-slate-100"
        >
          <AppIcon
            name="back"
            :size="18"
          />
        </Link>
        <span class="font-mono text-sm text-slate-400">{{ bug.code }}</span>
        <h1 class="font-display font-semibold text-slate-800">
          {{ bug.title }}
        </h1>
      </div>
    </template>

    <div class="grid gap-5 lg:grid-cols-3">
      <!-- Main -->
      <div class="space-y-5 lg:col-span-2">
        <div class="card p-5">
          <div class="mb-3 flex flex-wrap items-center gap-2">
            <Badge
              :label="bug.severity.label"
              :color="bug.severity.color"
            />
            <Badge
              :label="bug.priority.label"
              :color="bug.priority.color"
            />
            <Badge
              :label="bug.status.label"
              :color="bug.status.color"
            />
            <button
              v-if="bug.can?.update"
              class="btn-ghost ml-auto text-sm"
              @click="modal = true"
            >
              <AppIcon
                name="edit"
                :size="15"
              /> Sửa
            </button>
          </div>

          <section
            v-if="bug.description"
            class="mb-4"
          >
            <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
              Mô tả
            </h3>
            <p class="whitespace-pre-wrap text-sm text-slate-600">
              {{ bug.description }}
            </p>
          </section>
          <section
            v-if="bug.steps_to_reproduce"
            class="mb-4"
          >
            <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
              Các bước tái hiện
            </h3>
            <p class="whitespace-pre-wrap text-sm text-slate-600">
              {{ bug.steps_to_reproduce }}
            </p>
          </section>
          <div class="grid grid-cols-2 gap-4">
            <section v-if="bug.expected">
              <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                Mong đợi
              </h3><p class="text-sm text-slate-600">
                {{ bug.expected }}
              </p>
            </section>
            <section v-if="bug.actual">
              <h3 class="mb-1 text-xs font-semibold uppercase tracking-wide text-slate-400">
                Thực tế
              </h3><p class="text-sm text-slate-600">
                {{ bug.actual }}
              </p>
            </section>
          </div>
        </div>

        <div class="card p-5">
          <CommentThread
            :comments="bug.comments || []"
            commentable-type="bug"
            :commentable-id="bug.id"
          />
        </div>
      </div>

      <!-- Sidebar -->
      <div class="space-y-4">
        <div class="card space-y-3 p-5 text-sm">
          <div v-if="bug.can?.update">
            <label class="label">Trạng thái</label>
            <select
              :value="bug.status.value"
              class="input"
              @change="changeStatus"
            >
              <option
                v-for="o in options.status"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Dự án</span><span class="font-medium text-slate-700">{{ bug.project?.name || '—' }}</span>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-slate-400">Người sửa</span>
            <span
              v-if="bug.assignee"
              class="flex items-center gap-1.5"
            ><Avatar
              :name="bug.assignee.name"
              :src="bug.assignee.avatar_path"
              :size="22"
            /> <span class="font-medium text-slate-700">{{ bug.assignee.name }}</span></span>
            <span
              v-else
              class="text-slate-400"
            >Chưa giao</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Người báo cáo</span><span class="font-medium text-slate-700">{{ bug.reporter_display }}</span>
          </div>
          <div
            v-if="bug.environment"
            class="flex justify-between"
          >
            <span class="text-slate-400">Môi trường</span><span class="text-slate-700">{{ bug.environment }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-slate-400">Tạo lúc</span><span class="text-slate-700">{{ date(bug.created_at) }}</span>
          </div>
          <div
            v-if="bug.resolved_at"
            class="flex justify-between"
          >
            <span class="text-slate-400">Đã xử lý</span><span class="text-slate-700">{{ date(bug.resolved_at) }}</span>
          </div>
        </div>
      </div>
    </div>

    <BugFormModal
      :show="modal"
      :bug="bug"
      :projects="options.projects"
      :employees="options.employees"
      :severity-options="options.severity"
      :status-options="options.status"
      :priority-options="options.priority"
      @close="modal = false"
    />
  </AppLayout>
</template>
