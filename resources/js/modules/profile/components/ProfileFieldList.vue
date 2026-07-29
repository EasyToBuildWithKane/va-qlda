<script setup>
import { computed } from 'vue';
import { profileFieldState } from '../utils/profileDisplay';

const props = defineProps({
    /** @type {{ label: string, value?: *, mono?: boolean, href?: string|null }[]} */
    fields: { type: Array, required: true },
    /** Nhóm con (tiêu đề nhỏ trong panel) */
    groupTitle: { type: String, default: null },
});

const rows = computed(() =>
    props.fields.map((f) => {
        const state = profileFieldState(f.value);
        return {
            ...f,
            ...state,
        };
    }),
);
</script>

<template>
  <div class="profile-field-group">
    <p
      v-if="groupTitle"
      class="profile-field-group__title px-5 pt-4 pb-1 text-[12px] font-medium text-slate-500"
    >
      {{ groupTitle }}
    </p>
    <dl
      class="grid grid-cols-1 sm:grid-cols-2"
      :class="groupTitle ? 'pb-1' : ''"
    >
      <div
        v-for="row in rows"
        :key="row.label"
        class="profile-field min-w-0 border-b border-slate-100 px-5 py-3.5 sm:border-r sm:border-slate-100/80 last:border-b-0 odd:sm:border-r"
      >
        <dt class="text-[12px] font-medium text-slate-500">
          {{ row.label }}
        </dt>
        <dd class="mt-1 min-w-0">
          <a
            v-if="row.href && !row.empty"
            :href="row.href"
            class="block break-all text-[13px] font-normal text-slate-700 underline decoration-slate-300/80 underline-offset-2 transition-colors hover:text-brand hover:decoration-brand/40"
            :class="row.mono ? 'font-mono text-[12px]' : ''"
          >{{ row.text }}</a>
          <span
            v-else
            class="block break-words text-[13px] leading-snug"
            :class="[
              row.empty
                ? 'font-normal italic text-slate-400'
                : 'font-normal text-slate-700',
              row.mono && !row.empty ? 'font-mono text-[12px] tabular-nums' : '',
            ]"
          >{{ row.text }}</span>
        </dd>
      </div>
    </dl>
  </div>
</template>

<style scoped>
.profile-field-group + .profile-field-group .profile-field-group__title {
    margin-top: 0.5rem;
}

@media (min-width: 640px) {
    .profile-field:nth-last-child(-n + 2) {
        border-bottom-width: 0;
    }
}
</style>
