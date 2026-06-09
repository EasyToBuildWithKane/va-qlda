<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import BlockerAttachmentsBlock from '@/modules/project/components/BlockerAttachmentsBlock.vue';
import CommentThread from '@/shared/ui/CommentThread.vue';
import { datetime } from '@/composables/useFormat';

const props = defineProps({
    row: { type: Object, required: true },
    canComment: { type: Boolean, default: false },
});

const normalizeList = (val) => {
    if (Array.isArray(val)) return val;
    if (val?.data && Array.isArray(val.data)) return val.data;
    return [];
};

const normalizeEvidenceLinks = (val) => {
    const list = normalizeList(val);
    return list.map((item) => ({
        label: (item?.label ?? '').trim(),
        url: (item?.url ?? '').trim(),
    })).filter((item) => item.url);
};

const comments = computed(() => normalizeList(props.row.comments));
const attachments = computed(() => normalizeList(props.row.attachments));
const activities = computed(() => normalizeList(props.row.activities));
const displayEvidenceLinks = computed(() => normalizeEvidenceLinks(props.row.evidence_links));

const linkLabel = (item) => item.label || item.url;

const activityIcon = (event) => {
    const map = {
        created: 'add',
        updated: 'edit',
        status_changed: 'check',
        comment: 'comment',
        attachment: 'template',
    };
    return map[event] || 'report-history';
};

const timeline = computed(() => {
    const items = [...activities.value];
    if (!items.some((a) => a.event === 'created')) {
        items.push({
            id: 'raised',
            event: 'created',
            description: 'Phát hiện rủi ro / vướng mắc',
            employee: props.row.raised_by,
            created_at: props.row.raised_at,
        });
    }
    return items.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
});
</script>

<template>
  <div class="space-y-3">
    <p class="text-xs text-slate-500 dark:text-slate-400">
      Chỉ xem — chỉnh sửa và tải file minh chứng qua <span class="font-medium text-slate-600 dark:text-slate-300">Thao tác → Chỉnh sửa</span>.
    </p>
    <div class="grid gap-4 lg:grid-cols-2">
      <div class="space-y-4">
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Mô tả chi tiết
          </p>
          <p class="mt-1 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">
            {{ row.description || '—' }}
          </p>
        </div>

        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Nguyên nhân
          </p>
          <p class="mt-1 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">
            {{ row.root_cause || '—' }}
          </p>
        </div>

        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Hướng xử lý
          </p>
          <p class="mt-1 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300">
            {{ row.resolution || '—' }}
          </p>
        </div>

        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Link dẫn chứng
          </p>
          <ul
            v-if="displayEvidenceLinks.length"
            class="mt-2 space-y-1.5"
          >
            <li
              v-for="(link, index) in displayEvidenceLinks"
              :key="index"
            >
              <a
                :href="link.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex max-w-full items-center gap-1.5 text-sm font-medium text-brand hover:underline"
              >
                <AppIcon
                  name="dependency"
                  :size="14"
                  class="shrink-0"
                />
                <span class="truncate">{{ linkLabel(link) }}</span>
              </a>
            </li>
          </ul>
          <p
            v-else
            class="mt-1 text-xs text-slate-400"
          >
            Chưa có link dẫn chứng.
          </p>
        </div>

        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Ảnh & file đính kèm
          </p>
          <div class="mt-2">
            <BlockerAttachmentsBlock
              :blocker-id="row.id"
              :attachments="attachments"
              :can-upload="false"
              compact
            />
          </div>
        </div>
      </div>

      <div class="space-y-4">
        <div>
          <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
            Lịch sử cập nhật
          </p>
          <ul class="mt-2 max-h-48 space-y-2 overflow-y-auto pr-1">
            <li
              v-for="item in timeline"
              :key="item.id"
              class="flex gap-2 text-xs text-slate-600 dark:text-slate-300"
            >
              <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-700">
                <AppIcon
                  :name="activityIcon(item.event)"
                  :size="12"
                />
              </span>
              <div class="min-w-0 flex-1">
                <p class="font-medium text-slate-700 dark:text-slate-200">
                  {{ item.description }}
                </p>
                <p class="text-slate-400">
                  {{ datetime(item.created_at) }}
                  <span v-if="item.employee?.name"> · {{ item.employee.name }}</span>
                </p>
              </div>
            </li>
          </ul>
          <p
            v-if="row.resolved_at"
            class="mt-2 text-xs text-emerald-600 dark:text-emerald-400"
          >
            Giải quyết: {{ datetime(row.resolved_at) }}
          </p>
        </div>

        <CommentThread
          :comments="comments"
          commentable-type="blocker"
          :commentable-id="row.id"
          :can-comment="canComment"
          :can-moderate="!!row.can?.update"
        />
      </div>
    </div>
  </div>
</template>
