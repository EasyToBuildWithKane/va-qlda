<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import AppDialog from '@/Components/Ui/AppDialog.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const nav = computed(() => page.props.nav ?? []);
const flash = computed(() => page.props.flash ?? {});

// Highlight only the most specific matching item. A plain startsWith() would
// keep "/daily-reports" (Lịch sử) active while on "/daily-reports/today", so we
// pick the longest href that prefixes the current URL.
const allHrefs = computed(() =>
    nav.value.flatMap((g) => g.items.map((i) => i.href)).filter((h) => h !== '#'),
);
const activeHref = computed(() => {
    const url = page.url.split('?')[0];
    let best = '';
    for (const h of allHrefs.value) {
        if ((url === h || url.startsWith(h + '/')) && h.length > best.length) best = h;
    }
    return best;
});
const isActive = (href) => href !== '#' && href === activeHref.value;

const roleLabels = {
    admin: 'Quản trị viên',
    lead: 'Trưởng nhóm',
    member: 'Thành viên',
    viewer: 'Người xem',
};
const roleLabel = computed(() => roleLabels[user.value?.role] ?? user.value?.role ?? '');

// --- Whole-sidebar collapse (rail mode) ---
const RAIL_KEY = 'va-qlda.sidebar.rail';
const rail = ref(localStorage.getItem(RAIL_KEY) === '1');
watch(rail, (v) => localStorage.setItem(RAIL_KEY, v ? '1' : '0'));

// --- Per-group collapse ---
const COLLAPSE_KEY = 'va-qlda.sidebar.collapsed';
const collapsed = reactive(new Set(JSON.parse(localStorage.getItem(COLLAPSE_KEY) || '[]')));
watch(collapsed, () => localStorage.setItem(COLLAPSE_KEY, JSON.stringify([...collapsed])), { deep: true });

const isOpen = (group) => !collapsed.has(group.heading);
const toggleGroup = (group) => {
    collapsed.has(group.heading) ? collapsed.delete(group.heading) : collapsed.add(group.heading);
};

// --- Module status (đang phát triển / bảo trì …) ---
// Source of truth is App\Support\Navigation; here we only map it to styling.
const STATUS = {
    live: { label: 'Đang hoạt động', dot: 'bg-emerald-400', pill: 'bg-emerald-400/15 text-emerald-200' },
    dev: { label: 'Đang phát triển', dot: 'bg-accent', pill: 'bg-accent/15 text-accent-soft' },
    maintenance: { label: 'Đang bảo trì', dot: 'bg-sky-400', pill: 'bg-sky-400/15 text-sky-200' },
    planned: { label: 'Sắp ra mắt', dot: 'bg-white/30', pill: 'bg-white/10 text-brand-100/60' },
};
const statusOf = (item) => STATUS[item.status] ?? STATUS.live;
// "live" is the silent default — only the exceptions get a visible badge.
const showBadge = (item) => item.status && item.status !== 'live';
const isPlanned = (item) => item.status === 'planned' || item.href === '#';

// Legend at the foot of the sidebar — only show states actually in use.
const legend = computed(() => {
    const used = new Set(nav.value.flatMap((g) => g.items.map((i) => i.status || 'live')));
    return Object.entries(STATUS)
        .filter(([key]) => used.has(key))
        .map(([key, v]) => ({ key, ...v }));
});

const logout = () => router.post('/logout');
</script>

<template>
    <div class="min-h-screen flex bg-slate-50">
        <!-- Sidebar -->
        <aside
            class="shrink-0 bg-brand text-brand-100 flex flex-col transition-all duration-200"
            :class="rail ? 'w-16' : 'w-64'"
        >
            <!-- Brand + collapse toggle -->
            <div class="relative flex items-center justify-center border-b border-white/10" :class="rail ? 'h-16 px-0' : 'px-4 py-6'">
                <template v-if="rail">
                    <div class="h-9 w-9 shrink-0 rounded-btn bg-white/10 text-white grid place-items-center font-display font-bold">
                        VA
                    </div>
                </template>
                <template v-else>
                    <Link href="/dashboard" class="flex items-center justify-center">
                        <img src="/images/logo-2.png" alt="VAschools" class="h-16 w-auto object-contain" />
                    </Link>
                    <button
                        class="absolute right-2 top-2 grid h-8 w-8 place-items-center rounded-btn text-brand-100/70 hover:bg-white/10 hover:text-white transition"
                        title="Thu gọn thanh bên"
                        @click="rail = true"
                    >
                        <AppIcon name="collapse-left" :size="18" />
                    </button>
                </template>
            </div>

            <!-- Expand button (rail mode) -->
            <button
                v-if="rail"
                class="mx-auto mt-2 grid h-9 w-9 place-items-center rounded-btn text-brand-100/70 hover:bg-white/10 hover:text-white"
                title="Mở rộng thanh bên"
                @click="rail = false"
            >
                <AppIcon name="expand-left" :size="18" />
            </button>

            <!-- ===== Rail (collapsed) nav: flat icons ===== -->
            <nav v-if="rail" class="flex-1 overflow-y-auto py-3">
                <template v-for="(group, gi) in nav" :key="group.heading">
                    <div v-if="gi > 0" class="mx-3 my-2 border-t border-white/10"></div>
                    <component
                        :is="isPlanned(item) ? 'div' : Link"
                        v-for="item in group.items"
                        :key="item.label"
                        :href="isPlanned(item) ? undefined : item.href"
                        :title="`${item.label}${showBadge(item) ? ' · ' + statusOf(item).label : ''}`"
                        class="relative mx-auto my-0.5 grid h-10 w-10 place-items-center rounded-btn transition"
                        :class="[
                            isActive(item.href)
                                ? 'bg-white/10 text-white'
                                : 'text-brand-100/70 hover:bg-white/5 hover:text-white',
                            isPlanned(item) && 'opacity-50 cursor-not-allowed hover:bg-transparent',
                        ]"
                    >
                        <AppIcon :name="item.icon" :size="19" />
                        <span
                            v-if="showBadge(item)"
                            class="absolute right-1 top-1 h-2 w-2 rounded-full ring-2 ring-brand"
                            :class="statusOf(item).dot"
                        ></span>
                    </component>
                </template>
            </nav>

            <!-- ===== Expanded nav: group → item ===== -->
            <nav v-else class="flex-1 overflow-y-auto py-4 px-3 space-y-2">
                <div v-for="group in nav" :key="group.heading">
                    <button
                        type="button"
                        class="w-full flex items-center gap-2 px-2 py-1.5 rounded-btn text-xs font-bold uppercase tracking-wide text-brand-100/55 hover:text-white hover:bg-white/5 transition"
                        @click="toggleGroup(group)"
                    >
                        <AppIcon :name="group.icon" :size="16" />
                        <span class="flex-1 text-left">{{ group.heading }}</span>
                        <AppIcon
                            name="chevron"
                            :size="14"
                            class="transition-transform opacity-60"
                            :class="isOpen(group) ? 'rotate-90' : ''"
                        />
                    </button>

                    <ul v-show="isOpen(group)" class="mt-1 mb-1 space-y-0.5">
                        <li v-for="item in group.items" :key="item.label">
                            <component
                                :is="isPlanned(item) ? 'div' : Link"
                                :href="isPlanned(item) ? undefined : item.href"
                                :title="showBadge(item) ? statusOf(item).label : undefined"
                                class="group flex items-center gap-3 rounded-btn px-3 py-2.5 text-[15px] transition"
                                :class="[
                                    isActive(item.href)
                                        ? 'bg-white/12 text-white font-semibold shadow-sm'
                                        : 'text-brand-100/85 hover:bg-white/5 hover:text-white',
                                    isPlanned(item) && 'cursor-not-allowed text-brand-100/45 hover:bg-transparent hover:text-brand-100/45',
                                ]"
                            >
                                <AppIcon :name="item.icon" :size="18" class="shrink-0" />
                                <span class="truncate flex-1">{{ item.label }}</span>

                                <!-- Module status badge — only for the non-live exceptions -->
                                <span
                                    v-if="showBadge(item)"
                                    class="ml-auto shrink-0 inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium leading-none"
                                    :class="statusOf(item).pill"
                                >
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusOf(item).dot"></span>
                                    {{ statusOf(item).label }}
                                </span>
                                <span
                                    v-else-if="isActive(item.href)"
                                    class="ml-auto h-1.5 w-1.5 rounded-full bg-accent shrink-0"
                                ></span>
                            </component>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Module status legend -->
            <div v-if="!rail && legend.length" class="px-4 py-3 border-t border-white/10">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-brand-100/40 mb-2">Trạng thái module</p>
                <ul class="grid grid-cols-2 gap-x-3 gap-y-1.5">
                    <li v-for="s in legend" :key="s.key" class="flex items-center gap-1.5 text-[11px] text-brand-100/70">
                        <span class="h-1.5 w-1.5 rounded-full shrink-0" :class="s.dot"></span>
                        <span class="truncate">{{ s.label }}</span>
                    </li>
                </ul>
            </div>

            <div v-if="!rail" class="px-4 py-3 border-t border-white/10 text-[10px] text-brand-100/40">
                © {{ new Date().getFullYear() }} VAschools · Phòng Công nghệ
            </div>
        </aside>

        <!-- Main column -->
        <div class="flex-1 flex flex-col min-w-0">
            <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-6">
                <div>
                    <slot name="header">
                        <h1 class="font-display font-semibold text-slate-800">Bảng điều khiển</h1>
                    </slot>
                </div>

                <div class="flex items-center gap-3">
                    <div class="text-right leading-tight">
                        <p class="text-sm font-medium text-slate-800">{{ user?.display_name }}</p>
                        <p class="text-xs text-slate-400">{{ roleLabel }}</p>
                    </div>
                    <div class="h-9 w-9 rounded-full bg-brand-100 text-brand grid place-items-center font-semibold">
                        {{ (user?.display_name || '?').charAt(0).toUpperCase() }}
                    </div>
                    <button class="btn-ghost text-sm gap-1.5" @click="logout">
                        <AppIcon name="logout" :size="16" /> Đăng xuất
                    </button>
                </div>
            </header>

            <div v-if="flash.success" class="bg-success/10 text-success px-6 py-2 text-sm">{{ flash.success }}</div>
            <div v-if="flash.error" class="bg-danger/10 text-danger px-6 py-2 text-sm">{{ flash.error }}</div>

            <main class="flex-1 p-6 overflow-y-auto">
                <slot />
            </main>
        </div>

        <!-- App-wide dialog (replaces native alert/confirm/prompt) -->
        <AppDialog />
    </div>
</template>
