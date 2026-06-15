<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    rootOptions: { type: Array, default: () => [] },
});

const model = defineModel({
    type: Object,
    default: () => ({ query: '', rootId: null, role: 'all', status: 'all' }),
});

function patch(partial) {
    model.value = { ...model.value, ...partial };
}

const roleOptions = [
    { value: 'all', label: 'Tất cả vai trò' },
    { value: 'leaders', label: 'Quản lý' },
    { value: 'members', label: 'Thành viên' },
];

const statusOptions = [
    { value: 'all', label: 'Mọi trạng thái' },
    { value: 'active', label: 'Đang hoạt động' },
    { value: 'inactive', label: 'Ngừng hoạt động' },
];

const isActive = computed(() => Boolean(model.value.query)
    || model.value.rootId != null
    || model.value.role !== 'all'
    || model.value.status !== 'all');

function clearAll() {
    model.value = { query: '', rootId: null, role: 'all', status: 'all' };
}
</script>

<template>
  <section class="org-filter">
    <span class="org-filter__badge">
      <AppIcon
        name="filter"
        :size="14"
      />
      Bộ lọc
    </span>

    <label class="org-filter__search">
      <AppIcon
        name="search"
        :size="15"
        class="org-filter__search-icon"
      />
      <input
        :value="model.query"
        type="search"
        placeholder="Tìm nhân sự, chức danh, mã NV…"
        aria-label="Tìm trên sơ đồ tổ chức"
        class="org-filter__input"
        @input="patch({ query: $event.target.value })"
      >
    </label>

    <div class="org-filter__select">
      <select
        :value="model.rootId ?? ''"
        aria-label="Lọc theo Nhóm"
        class="org-filter__control"
        @change="patch({ rootId: $event.target.value ? Number($event.target.value) : null })"
      >
        <option value="">
          Tất cả Nhóm
        </option>
        <option
          v-for="root in rootOptions"
          :key="root.id"
          :value="root.id"
        >
          {{ root.name }}
        </option>
      </select>
      <AppIcon
        name="chevron-down"
        :size="15"
        class="org-filter__caret"
      />
    </div>

    <div class="org-filter__select">
      <select
        :value="model.role"
        aria-label="Lọc theo vai trò"
        class="org-filter__control"
        @change="patch({ role: $event.target.value })"
      >
        <option
          v-for="opt in roleOptions"
          :key="opt.value"
          :value="opt.value"
        >
          {{ opt.label }}
        </option>
      </select>
      <AppIcon
        name="chevron-down"
        :size="15"
        class="org-filter__caret"
      />
    </div>

    <div class="org-filter__select">
      <select
        :value="model.status"
        aria-label="Lọc theo trạng thái"
        class="org-filter__control"
        @change="patch({ status: $event.target.value })"
      >
        <option
          v-for="opt in statusOptions"
          :key="opt.value"
          :value="opt.value"
        >
          {{ opt.label }}
        </option>
      </select>
      <AppIcon
        name="chevron-down"
        :size="15"
        class="org-filter__caret"
      />
    </div>

    <button
      v-if="isActive"
      type="button"
      class="org-filter__clear"
      @click="clearAll"
    >
      <AppIcon
        name="close"
        :size="14"
      />
      Xoá lọc
    </button>
  </section>
</template>

<style scoped>
.org-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 0.65rem;
    border-radius: 14px;
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(8px);
    border: 1px solid rgba(148, 163, 184, 0.3);
    box-shadow: 0 8px 22px -20px rgba(15, 23, 42, 0.5);
}

.org-filter__badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    height: 40px;
    padding: 0 0.7rem;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    color: #9a0036;
    background: rgba(154, 0, 54, 0.08);
}

.org-filter__search {
    position: relative;
    display: flex;
    align-items: center;
    flex: 1 1 200px;
    min-width: 180px;
}

.org-filter__search-icon {
    position: absolute;
    left: 0.65rem;
    color: #94a3b8;
    pointer-events: none;
}

.org-filter__input {
    width: 100%;
    height: 40px;
    padding: 0 0.75rem 0 2.1rem;
    border-radius: 10px;
    font-size: 13px;
    color: #0f172a;
    background: #fff;
    border: 1px solid rgba(148, 163, 184, 0.4);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.org-filter__input:focus {
    outline: none;
    border-color: #9a0036;
    box-shadow: 0 0 0 3px rgba(154, 0, 54, 0.14);
}

.org-filter__select {
    position: relative;
    flex: 1 1 160px;
    min-width: 150px;
}

.org-filter__control {
    width: 100%;
    height: 40px;
    padding: 0 2rem 0 0.75rem;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    color: #334155;
    background: #fff;
    border: 1px solid rgba(148, 163, 184, 0.4);
    appearance: none;
    cursor: pointer;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.org-filter__control:focus {
    outline: none;
    border-color: #9a0036;
    box-shadow: 0 0 0 3px rgba(154, 0, 54, 0.14);
}

.org-filter__caret {
    position: absolute;
    right: 0.6rem;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}

.org-filter__clear {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    height: 40px;
    padding: 0 0.8rem;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    background: rgba(148, 163, 184, 0.12);
    transition: color 0.2s ease, background 0.2s ease;
}

.org-filter__clear:hover {
    color: #9a0036;
    background: rgba(154, 0, 54, 0.08);
}
</style>
