<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    profile: { type: Object, required: true },
});

const rows = computed(() => {
    const p = props.profile;
    const s = p.socials || {};
    return [
        { icon: 'mail', label: 'Email', value: p.email || '—', href: p.email ? `mailto:${p.email}` : null },
        { icon: 'phone', label: 'Điện thoại', value: p.phone || '—', href: p.phone ? `tel:${p.phone}` : null },
        { icon: 'map-pin', label: 'Địa điểm', value: p.location || '—', href: null },
        { icon: 'github', label: 'GitHub', value: s.github || '—', href: s.github || null },
        { icon: 'linkedin', label: 'LinkedIn', value: s.linkedin || '—', href: s.linkedin || null },
        { icon: 'external-link', label: 'Portfolio', value: s.portfolio || '—', href: s.portfolio || null },
        { icon: 'globe', label: 'Website', value: s.website || '—', href: s.website || null },
    ];
});

const hasBio = computed(() => Boolean(props.profile.bio));
</script>

<template>
  <section class="rounded-2xl border border-slate-200/70 bg-white shadow-sm">
    <header class="flex items-center gap-2.5 border-b border-slate-100 px-5 py-3.5">
      <AppIcon
        name="account"
        :size="16"
        class="text-slate-400"
      />
      <h2 class="text-sm font-semibold text-slate-800">
        Liên hệ
      </h2>
    </header>

    <div class="space-y-4 p-5">
      <div v-if="hasBio">
        <p class="mb-1 text-[11px] uppercase tracking-wide text-slate-400">
          Giới thiệu
        </p>
        <p class="text-[13px] leading-relaxed text-slate-600">
          {{ profile.bio }}
        </p>
      </div>
      <p
        v-else
        class="text-[13px] text-slate-400"
      >
        Chưa có phần giới thiệu.
      </p>

      <dl class="grid grid-cols-1 gap-x-6 gap-y-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
        <div
          v-for="r in rows"
          :key="r.label"
          class="flex items-start gap-2.5 min-w-0"
        >
          <div class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500">
            <AppIcon
              :name="r.icon"
              :size="13"
            />
          </div>
          <div class="min-w-0 flex-1">
            <dt class="text-[11px] uppercase tracking-wide text-slate-400">
              {{ r.label }}
            </dt>
            <dd class="min-w-0">
              <a
                v-if="r.href"
                :href="r.href"
                target="_blank"
                rel="noopener noreferrer"
                class="block break-all text-[13px] font-medium text-slate-700 hover:text-brand"
              >{{ r.value }}</a>
              <span
                v-else
                class="block break-words text-[13px] font-medium text-slate-700"
              >{{ r.value }}</span>
            </dd>
          </div>
        </div>
      </dl>
    </div>
  </section>
</template>
