<script setup>
import { Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    title:     { type: String, required: true },
    subtitle:  { type: String,  default: '' },
    icon:      { type: String,  default: '' },
    iconColor: { type: String,  default: 'brand' },
    badge:     { type: [String, Number], default: null },
    backHref:  { type: String,  default: '' },
});

const colorMap = {
    brand:   'bg-brand/10 text-brand',
    rose:    'bg-rose-100 text-rose-600',
    pink:    'bg-pink-100 text-pink-600',
    red:     'bg-red-100 text-red-600',
    orange:  'bg-orange-100 text-orange-600',
    amber:   'bg-amber-100 text-amber-600',
    yellow:  'bg-yellow-100 text-yellow-600',
    lime:    'bg-lime-100 text-lime-600',
    green:   'bg-green-100 text-green-600',
    emerald: 'bg-emerald-100 text-emerald-600',
    teal:    'bg-teal-100 text-teal-600',
    cyan:    'bg-cyan-100 text-cyan-600',
    sky:     'bg-sky-100 text-sky-600',
    blue:    'bg-blue-100 text-blue-600',
    indigo:  'bg-indigo-100 text-indigo-600',
    violet:  'bg-violet-100 text-violet-600',
    purple:  'bg-purple-100 text-purple-600',
    fuchsia: 'bg-fuchsia-100 text-fuchsia-600',
    slate:   'bg-slate-100 text-slate-500',
    stone:   'bg-stone-100 text-stone-500',
};
</script>

<template>
  <div class="flex items-center gap-2.5 min-w-0">
    <!-- Back button -->
    <Link
      v-if="backHref"
      :href="backHref"
      class="shrink-0 grid h-8 w-8 place-items-center rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"
    >
      <AppIcon
        name="back"
        :size="17"
      />
    </Link>

    <!-- Colour icon box -->
    <div
      v-if="icon"
      class="shrink-0 h-8 w-8 rounded-lg flex items-center justify-center"
      :class="colorMap[iconColor] ?? colorMap.brand"
    >
      <AppIcon
        :name="icon"
        :size="15"
      />
    </div>

    <!-- Title + subtitle -->
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-2 flex-wrap">
        <h1 class="text-[15px] font-semibold text-slate-800 leading-tight truncate">
          {{ title }}
        </h1>
        <span
          v-if="badge !== null && badge !== undefined && badge !== ''"
          class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold leading-none bg-brand/10 text-brand"
        >
          {{ badge }}
        </span>
      </div>
      <p
        v-if="subtitle"
        class="text-[12px] text-slate-400 leading-tight mt-0.5 truncate"
      >
        {{ subtitle }}
      </p>
    </div>

    <!-- Optional slot for extra elements (action buttons, etc.) -->
    <slot />
  </div>
</template>
