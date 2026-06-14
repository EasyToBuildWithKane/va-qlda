<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    profile: { type: Object, required: true },
});

const rows = computed(() => {
    const out = [];
    if (props.profile.email) {
        out.push({ icon: 'mail', label: 'Email', value: props.profile.email, href: `mailto:${props.profile.email}` });
    }
    if (props.profile.phone) {
        out.push({ icon: 'phone', label: 'Điện thoại', value: props.profile.phone, href: `tel:${props.profile.phone}` });
    }
    if (props.profile.location) {
        out.push({ icon: 'map-pin', label: 'Địa điểm', value: props.profile.location, href: null });
    }
    return out;
});

const socials = computed(() => {
    const s = props.profile.socials || {};
    return [
        { key: 'github', icon: 'github', label: 'GitHub', href: s.github },
        { key: 'linkedin', icon: 'linkedin', label: 'LinkedIn', href: s.linkedin },
        { key: 'portfolio', icon: 'external-link', label: 'Portfolio', href: s.portfolio },
        { key: 'website', icon: 'globe', label: 'Website', href: s.website },
    ].filter((x) => x.href);
});

const hasAny = computed(() => rows.value.length || socials.value.length || props.profile.bio);
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
      <p
        v-if="profile.bio"
        class="text-[13px] leading-relaxed text-slate-600"
      >
        {{ profile.bio }}
      </p>

      <p
        v-if="!hasAny"
        class="text-[13px] text-slate-400"
      >
        Chưa có thông tin liên hệ.
      </p>

      <ul
        v-if="rows.length"
        class="space-y-3"
      >
        <li
          v-for="r in rows"
          :key="r.label"
          class="flex items-start gap-2.5"
        >
          <div class="mt-0.5 grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-500">
            <AppIcon
              :name="r.icon"
              :size="13"
            />
          </div>
          <div class="min-w-0">
            <p class="text-[11px] uppercase tracking-wide text-slate-400">
              {{ r.label }}
            </p>
            <a
              v-if="r.href"
              :href="r.href"
              class="block truncate text-[13px] font-medium text-slate-700 hover:text-brand"
            >{{ r.value }}</a>
            <p
              v-else
              class="truncate text-[13px] font-medium text-slate-700"
            >
              {{ r.value }}
            </p>
          </div>
        </li>
      </ul>

      <div
        v-if="socials.length"
        class="flex flex-wrap gap-2 border-t border-slate-100 pt-4"
      >
        <a
          v-for="s in socials"
          :key="s.key"
          :href="s.href"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1.5 text-[12px] font-medium text-slate-600 transition-colors hover:border-brand/30 hover:text-brand"
        >
          <AppIcon
            :name="s.icon"
            :size="13"
          />
          {{ s.label }}
        </a>
      </div>
    </div>
  </section>
</template>
