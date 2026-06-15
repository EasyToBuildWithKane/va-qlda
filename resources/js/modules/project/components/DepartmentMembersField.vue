<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import SearchMultiSelect from '@/shared/ui/SearchMultiSelect.vue';

const props = defineProps({
    employees: { type: Array, default: () => [] },
    memberIds: { type: Array, default: () => [] },
    managerId: { type: [Number, String, null], default: null },
    disabled: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:memberIds', 'update:managerId']);

const memberIdsModel = computed({
    get: () => props.memberIds ?? [],
    set: (val) => emit('update:memberIds', val),
});

const selectedDetails = computed(() => {
    const ids = new Set((props.memberIds ?? []).map((id) => Number(id)));
    return props.employees.filter((e) => ids.has(Number(e.id)));
});

const memberCount = computed(() => selectedDetails.value.length);

function removeMember(id) {
    const next = props.memberIds.filter((x) => Number(x) !== Number(id));
    emit('update:memberIds', next);
    if (Number(props.managerId) === Number(id)) {
        emit('update:managerId', null);
    }
}

function setManager(id) {
    emit('update:managerId', id);
    if (!props.memberIds.some((x) => Number(x) === Number(id))) {
        emit('update:memberIds', [...props.memberIds, id]);
    }
}

function onMemberIdsChange(next) {
    emit('update:memberIds', next);
    if (props.managerId && !next.some((x) => Number(x) === Number(props.managerId))) {
        emit('update:managerId', null);
    }
}
</script>

<template>
  <section class="rounded-xl border border-slate-200/90 bg-gradient-to-b from-slate-50/80 to-white p-4">
    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
      <div>
        <h3 class="flex items-center gap-1.5 text-sm font-semibold text-slate-800">
          Thành viên phòng ban
          <FieldTooltip text="Gán nhân sự thuộc phòng ban. Trưởng phòng nên nằm trong danh sách thành viên." />
        </h3>
        <p class="mt-0.5 text-xs text-slate-500">
          Tìm theo tên, mã hoặc chức danh — chọn nhiều người cùng lúc.
        </p>
      </div>
      <span
        class="inline-flex items-center gap-1 rounded-full bg-brand/10 px-2.5 py-1 text-[11px] font-semibold text-brand"
      >
        <AppIcon
          name="account"
          :size="12"
        />
        {{ memberCount }} thành viên
      </span>
    </div>

    <SearchMultiSelect
      :model-value="memberIdsModel"
      :options="employees"
      :disabled="disabled"
      placeholder="Tìm & thêm thành viên…"
      search-placeholder="Tên, mã NV, chức danh…"
      :show-avatar="true"
      :search-keys="['name', 'code', 'role_title', 'email']"
      subtitle-key="role_title"
      control-size="md"
      :max-chips="4"
      @update:model-value="onMemberIdsChange"
    />

    <p
      v-if="error"
      class="mt-2 text-xs text-danger"
    >
      {{ error }}
    </p>

    <div
      v-if="selectedDetails.length"
      class="mt-4 max-h-56 space-y-2 overflow-y-auto pr-0.5"
    >
      <div
        v-for="person in selectedDetails"
        :key="person.id"
        class="flex items-center gap-3 rounded-lg border border-slate-100 bg-white px-3 py-2.5 shadow-sm transition hover:border-slate-200"
      >
        <Avatar
          :name="person.name"
          :src="person.avatar_path"
          :size="36"
        />
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <p class="truncate text-sm font-medium text-slate-800">
              {{ person.name }}
            </p>
            <span
              v-if="Number(managerId) === Number(person.id)"
              class="inline-flex items-center gap-0.5 rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700"
            >
              <AppIcon
                name="star"
                :size="10"
              />
              Trưởng phòng
            </span>
          </div>
          <p class="truncate text-xs text-slate-500">
            <span
              v-if="person.code"
              class="font-mono text-slate-400"
            >{{ person.code }}</span>
            <span v-if="person.code && (person.role_title || person.email)"> · </span>
            {{ person.role_title || person.email || '—' }}
          </p>
        </div>
        <div class="flex shrink-0 items-center gap-1">
          <button
            v-if="Number(managerId) !== Number(person.id)"
            type="button"
            class="rounded-md px-2 py-1 text-[11px] font-medium text-sky-600 transition hover:bg-sky-50"
            title="Đặt làm trưởng phòng"
            @click="setManager(person.id)"
          >
            Trưởng phòng
          </button>
          <button
            type="button"
            class="grid h-8 w-8 place-items-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-600"
            title="Gỡ khỏi phòng ban"
            @click="removeMember(person.id)"
          >
            <AppIcon
              name="close"
              :size="14"
            />
          </button>
        </div>
      </div>
    </div>

    <div
      v-else
      class="mt-4 flex flex-col items-center gap-2 rounded-lg border border-dashed border-slate-200 bg-white/60 px-4 py-8 text-center"
    >
      <AppIcon
        name="account"
        :size="28"
        class="text-slate-200"
      />
      <p class="text-sm text-slate-500">
        Chưa có thành viên. Dùng ô tìm phía trên để thêm nhân sự.
      </p>
      <p class="text-xs text-slate-400">
        Có thể bổ sung sau khi tạo phòng ban.
      </p>
    </div>
  </section>
</template>
