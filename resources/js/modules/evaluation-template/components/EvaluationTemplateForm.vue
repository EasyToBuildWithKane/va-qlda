<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import { useConfirmClose } from '@/composables/useConfirmClose';
import MultiCatalogSelect from '@/modules/evaluation-template/components/MultiCatalogSelect.vue';
import ToggleSwitch from '@/shared/ui/form/ToggleSwitch.vue';
import { matchesSearchQuery } from '@/shared/utils/normalizeSearchKey';

const props = defineProps({
    /** When set (edit), hydrate from this template. */
    template: { type: Object, default: null },
    criteriaOptions: { type: Array, default: () => [] },
    jobTitles: { type: Array, default: () => [] },
    jobRanks: { type: Array, default: () => [] },
    fieldTypeOptions: { type: Array, default: () => [] },
    /** page = full Create/Edit card; modal = embed in Modal */
    layout: { type: String, default: 'page' },
    /** For modal: remount/hydrate when true */
    active: { type: Boolean, default: true },
});

const emit = defineEmits(['cancel', 'success']);

const isEdit = computed(() => Boolean(props.template?.id));
const isPage = computed(() => props.layout === 'page');

const form = useForm({
    name: '',
    description: '',
    is_active: true,
    titles: [],
    ranks: [],
    criteria: [],
    fields: [],
});

const criteriaQuery = ref('');
/** field_key → show placeholder / help_text editors */
const fieldExtrasOpen = ref({});
/** Đối tượng áp dụng: chức danh XOR cấp bậc */
const targetMode = ref('title'); // 'title' | 'rank'

const selectedCatalogIds = computed(() => new Set(
    form.criteria
        .filter((c) => c.source === 'catalog' && c.criterion_id != null)
        .map((c) => Number(c.criterion_id)),
));

const filteredCatalog = computed(() => {
    const q = criteriaQuery.value.trim();
    let list = props.criteriaOptions || [];
    if (q) {
        list = list.filter((c) => matchesSearchQuery(
            [c.criteria_name, c.criteria_code, c.category, c.department_name, c.label],
            q,
        ));
    }
    return list;
});

const WEIGHT_PERCENT_OPTIONS = [10, 20, 30, 40, 50, 60, 70, 80, 90, 100];

const weightSum = computed(() => form.criteria.reduce((s, c) => s + (Number(c.weight) || 0), 0));
const weightSumOk = computed(() => weightSum.value === 100);

const dirty = computed(() => form.isDirty);
const { requestClose } = useConfirmClose({
    dirty,
    message: 'Bạn có thay đổi chưa lưu. Rời trang?',
    onConfirmClose: () => {
        if (isPage.value) {
            router.visit(route('workspace.evaluation-templates.index'));
            return;
        }
        emit('cancel');
    },
});

const titlesModel = computed({
    get: () => form.titles,
    set: (v) => {
        form.titles = v;
        if ((v || []).length) {
            targetMode.value = 'title';
            if (form.ranks.length) form.ranks = [];
        }
    },
});
const ranksModel = computed({
    get: () => form.ranks,
    set: (v) => {
        form.ranks = v;
        if ((v || []).length) {
            targetMode.value = 'rank';
            if (form.titles.length) form.titles = [];
        }
    },
});

function setTargetMode(mode) {
    if (mode !== 'title' && mode !== 'rank') return;
    if (targetMode.value === mode) return;
    targetMode.value = mode;
    if (mode === 'title') {
        form.ranks = [];
    } else {
        form.titles = [];
    }
}

function emptyCustomCriterion() {
    return {
        source: 'custom',
        criterion_id: null,
        custom_name: '',
        custom_category: '',
        custom_description: '',
        weight: 10,
        required_score_label: '',
        include_in_total: true,
    };
}

/** Normalize stored weight → 10…100 step 10 for the percent select. */
function normalizeWeightPercent(raw) {
    const n = Number(raw);
    if (!Number.isFinite(n) || n <= 0) return 10;
    if (WEIGHT_PERCENT_OPTIONS.includes(n)) return n;
    if (n > 0 && n <= 1) {
        const pct = Math.round(n * 10) * 10;
        return WEIGHT_PERCENT_OPTIONS.includes(pct) ? pct : 10;
    }
    if (n < 10) {
        const pct = Math.min(100, Math.round(n) * 10);
        return WEIGHT_PERCENT_OPTIONS.includes(pct) ? pct : 10;
    }
    const snapped = Math.min(100, Math.max(10, Math.round(n / 10) * 10));
    return WEIGHT_PERCENT_OPTIONS.includes(snapped) ? snapped : 10;
}

function weightSelectOptions(current) {
    const cur = Number(current);
    if (Number.isFinite(cur) && cur > 0 && !WEIGHT_PERCENT_OPTIONS.includes(cur)) {
        return [...WEIGHT_PERCENT_OPTIONS, cur].sort((a, b) => a - b);
    }
    return WEIGHT_PERCENT_OPTIONS;
}

function emptyField() {
    return {
        field_key: `field_${Date.now().toString(36)}`,
        label: '',
        field_type: 'text',
        is_required: false,
        options: [],
        help_text: '',
        placeholder: '',
        optionsText: '',
    };
}

function fieldTypeLabel(type) {
    const opt = (props.fieldTypeOptions || []).find((o) => o.value === type);
    return opt?.label || type;
}

const SELECT_OPTIONS_PLACEHOLDER = 'Xuất sắc\nĐạt\nChưa đạt';

function slugifyFieldKey(label, idx) {
    const base = String(label || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_')
        .replace(/^_|_$/g, '')
        .slice(0, 40);
    return base || `field_${idx + 1}`;
}

function catalogById(id) {
    return (props.criteriaOptions || []).find((c) => Number(c.id) === Number(id)) || null;
}

function resolveCatalogName(options, code) {
    const hit = (options || []).find((o) => String(o.code) === String(code));
    return hit?.name || '';
}

function scoreLevelsFor(itemOrRow) {
    if (Array.isArray(itemOrRow?.score_levels) && itemOrRow.score_levels.length) {
        return itemOrRow.score_levels;
    }
    if (itemOrRow?.source === 'catalog' && itemOrRow?.criterion_id != null) {
        return catalogById(itemOrRow.criterion_id)?.score_levels || [];
    }
    return [];
}

/** Prefer “Đạt yêu cầu” / “Đạt”, else middle level of the criterion scale. */
function defaultRequiredScoreLabel(levels) {
    if (!Array.isArray(levels) || !levels.length) return '';
    const byLabel = levels.find((l) => {
        const t = String(l.label || '').toLowerCase();
        return t.includes('đạt yêu cầu') || t === 'đạt';
    });
    if (byLabel?.label) return byLabel.label;
    const mid = levels[Math.floor((levels.length - 1) / 2)];
    return mid?.label || levels[0]?.label || '';
}

function selectedScoreLevel(row) {
    const levels = scoreLevelsFor(row);
    if (!levels.length || !row?.required_score_label) return null;
    return levels.find((l) => l.label === row.required_score_label) || null;
}

function formatLevelPoints(level) {
    if (!level || level.weight == null || level.weight === '') return null;
    const w = Number(level.weight);
    if (!Number.isFinite(w)) return null;
    return Number.isInteger(w) ? String(w) : String(w);
}

function hydrateFromTemplate(t) {
    if (!t) {
        form.reset();
        form.clearErrors();
        form.name = '';
        form.description = '';
        form.is_active = true;
        form.titles = [];
        form.ranks = [];
        form.criteria = [];
        form.fields = [];
        criteriaQuery.value = '';
        fieldExtrasOpen.value = {};
        targetMode.value = 'title';
        return;
    }

    form.name = t.name || '';
    form.description = t.description || '';
    form.is_active = t.is_active !== false;
    const titles = (t.titles || []).map((x) => ({
        code: x.code,
        name: x.name || resolveCatalogName(props.jobTitles, x.code) || x.code,
        hrm_uuid: x.hrm_uuid ?? null,
        source: x.source || 'directory',
    }));
    const ranks = (t.ranks || []).map((x) => ({
        code: x.code,
        name: x.name || resolveCatalogName(props.jobRanks, x.code) || x.code,
        hrm_uuid: x.hrm_uuid ?? null,
        source: x.source || 'directory',
    }));
    // XOR: ưu tiên chức danh nếu cả hai đều có (dữ liệu cũ)
    if (titles.length && ranks.length) {
        form.titles = titles;
        form.ranks = [];
        targetMode.value = 'title';
    } else if (ranks.length) {
        form.titles = [];
        form.ranks = ranks;
        targetMode.value = 'rank';
    } else {
        form.titles = titles;
        form.ranks = [];
        targetMode.value = 'title';
    }
    form.criteria = (t.criteria || []).map((c) => {
        if (c.source === 'custom' || c.is_custom) {
            return {
                source: 'custom',
                criterion_id: null,
                custom_name: c.custom_name || c.criteria_name || '',
                custom_category: c.custom_category || c.category || '',
                custom_description: c.custom_description || c.description || '',
                weight: normalizeWeightPercent(c.weight),
                required_score_label: c.required_score_label || '',
                include_in_total: c.include_in_total !== false,
            };
        }
        const catalog = catalogById(c.criterion_id);
        const levels = scoreLevelsFor(catalog || c);
        return {
            source: 'catalog',
            criterion_id: Number(c.criterion_id),
            criteria_name: c.criteria_name || catalog?.criteria_name || '',
            criteria_code: c.criteria_code || catalog?.criteria_code || '',
            category: c.category || catalog?.category || '',
            score_levels: levels,
            weight: normalizeWeightPercent(c.weight),
            required_score_label: c.required_score_label || defaultRequiredScoreLabel(levels),
            include_in_total: c.include_in_total !== false,
        };
    });
    form.fields = (t.fields || []).map((f) => ({
        field_key: f.field_key,
        label: f.label || '',
        field_type: f.field_type || 'text',
        is_required: Boolean(f.is_required),
        options: Array.isArray(f.options) ? [...f.options] : [],
        help_text: f.help_text || '',
        placeholder: f.placeholder || '',
        optionsText: Array.isArray(f.options) ? f.options.join('\n') : '',
    }));
    const extras = {};
    form.fields.forEach((f) => {
        extras[f.field_key] = Boolean(f.placeholder?.trim() || f.help_text?.trim());
    });
    fieldExtrasOpen.value = extras;
    form.clearErrors();
    criteriaQuery.value = '';
}

onMounted(() => {
    if (isPage.value) {
        hydrateFromTemplate(props.template);
    }
});

watch(() => props.active, (open) => {
    if (open && !isPage.value) {
        hydrateFromTemplate(props.template);
    }
});

function toggleCatalog(item) {
    const id = Number(item.id);
    if (selectedCatalogIds.value.has(id)) {
        form.criteria = form.criteria.filter(
            (c) => !(c.source === 'catalog' && Number(c.criterion_id) === id),
        );
        return;
    }
    const levels = scoreLevelsFor(item);
    form.criteria.push({
        source: 'catalog',
        criterion_id: id,
        criteria_name: item.criteria_name,
        criteria_code: item.criteria_code,
        category: item.category || '',
        score_levels: levels,
        weight: 10,
        required_score_label: defaultRequiredScoreLabel(levels),
        include_in_total: true,
    });
}

function addCustomCriterion() {
    form.criteria.push(emptyCustomCriterion());
}

function removeCriterion(idx) {
    form.criteria.splice(idx, 1);
}

function moveCriterion(idx, dir) {
    const next = idx + dir;
    if (next < 0 || next >= form.criteria.length) return;
    const arr = [...form.criteria];
    const [row] = arr.splice(idx, 1);
    arr.splice(next, 0, row);
    form.criteria = arr;
}

function addField() {
    const row = emptyField();
    form.fields.push(row);
    fieldExtrasOpen.value = { ...fieldExtrasOpen.value, [row.field_key]: false };
}

function removeField(idx) {
    const key = form.fields[idx]?.field_key;
    form.fields.splice(idx, 1);
    if (key && fieldExtrasOpen.value[key] != null) {
        const next = { ...fieldExtrasOpen.value };
        delete next[key];
        fieldExtrasOpen.value = next;
    }
}

function moveField(idx, dir) {
    const next = idx + dir;
    if (next < 0 || next >= form.fields.length) return;
    const arr = [...form.fields];
    const [row] = arr.splice(idx, 1);
    arr.splice(next, 0, row);
    form.fields = arr;
}

function toggleFieldExtras(key) {
    fieldExtrasOpen.value = {
        ...fieldExtrasOpen.value,
        [key]: !fieldExtrasOpen.value[key],
    };
}


function needsOptions(type) {
    return type === 'select';
}

function needsPlaceholder(type) {
    return ['text', 'textarea', 'number', 'date'].includes(type);
}

function buildPayload() {
    const titles = targetMode.value === 'title'
        ? form.titles.map((t) => ({
            code: t.code,
            name: t.name || resolveCatalogName(props.jobTitles, t.code) || t.code,
            hrm_uuid: t.hrm_uuid ?? null,
            source: t.source || 'directory',
        }))
        : [];
    const ranks = targetMode.value === 'rank'
        ? form.ranks.map((r) => ({
            code: r.code,
            name: r.name || resolveCatalogName(props.jobRanks, r.code) || r.code,
            hrm_uuid: r.hrm_uuid ?? null,
            source: r.source || 'directory',
        }))
        : [];

    return {
        name: form.name,
        description: form.description || null,
        is_active: form.is_active,
        titles,
        ranks,
        criteria: form.criteria.map((c, idx) => {
            if (c.source === 'catalog') {
                return {
                    source: 'catalog',
                    criterion_id: Number(c.criterion_id),
                    weight: Number(c.weight) || 10,
                    required_score_label: c.required_score_label || null,
                    include_in_total: c.include_in_total !== false,
                    sort_order: idx,
                };
            }
            return {
                source: 'custom',
                custom_name: c.custom_name,
                custom_code: null,
                custom_category: c.custom_category || null,
                custom_description: c.custom_description || null,
                weight: Number(c.weight) || 10,
                required_score_label: c.required_score_label || null,
                include_in_total: c.include_in_total !== false,
                sort_order: idx,
            };
        }),
        fields: form.fields.map((f, idx) => {
            const options = needsOptions(f.field_type)
                ? String(f.optionsText || '')
                    .split(/\r?\n/)
                    .map((s) => s.trim())
                    .filter(Boolean)
                : [];
            return {
                field_key: f.field_key || slugifyFieldKey(f.label, idx),
                label: f.label,
                field_type: f.field_type,
                is_required: Boolean(f.is_required),
                options,
                help_text: f.help_text || null,
                placeholder: f.placeholder || null,
                sort_order: idx,
            };
        }),
    };
}

function submit() {
    const payload = buildPayload();
    const opts = {
        preserveScroll: true,
        onSuccess: () => emit('success'),
    };

    if (isEdit.value) {
        form.transform(() => payload).put(
            route('workspace.evaluation-templates.update', props.template.id),
            opts,
        );
        return;
    }

    form.transform(() => payload).post(route('workspace.evaluation-templates.store'), opts);
}

defineExpose({ requestClose, dirty, form });
</script>

<template>
  <div :class="isPage ? 'overflow-hidden rounded-card border border-slate-200/80 bg-white shadow-sm' : ''">
    <form
      class="space-y-4"
      :class="isPage ? 'p-4 sm:p-5' : ''"
      @submit.prevent="submit"
    >
      <!-- 1. Thông tin chung + đối tượng -->
      <section class="rounded-xl border border-slate-200 bg-gradient-to-b from-slate-50/80 to-white p-3 sm:p-3.5">
        <div class="mb-2.5 flex items-center justify-between gap-3">
          <div class="flex min-w-0 items-center gap-2">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-brand/10 text-brand">
              <AppIcon
                name="documents"
                :size="13"
              />
            </span>
            <h3 class="text-sm font-semibold text-slate-800">
              Thông tin chung
            </h3>
          </div>
          <label class="inline-flex shrink-0 cursor-pointer items-center gap-2">
            <span
              class="text-xs font-medium"
              :class="form.is_active ? 'text-emerald-700' : 'text-slate-500'"
            >
              {{ form.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}
            </span>
            <ToggleSwitch v-model="form.is_active" />
          </label>
        </div>

        <div class="grid gap-2.5 sm:grid-cols-12 sm:items-start">
          <div class="sm:col-span-5">
            <label class="mb-1 block text-[11px] font-medium text-slate-500">
              Tên mẫu <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.name"
              type="text"
              class="input h-9 w-full text-sm"
              placeholder="Tên mẫu đánh giá"
              required
            >
            <p
              v-if="form.errors.name"
              class="mt-1 text-xs text-rose-600"
            >
              {{ form.errors.name }}
            </p>
          </div>
          <div class="sm:col-span-7">
            <label class="mb-1 block text-[11px] font-medium text-slate-500">
              Mô tả
            </label>
            <input
              v-model="form.description"
              type="text"
              class="input h-9 w-full text-sm"
              placeholder="Mô tả ngắn (tuỳ chọn)"
            >
          </div>
        </div>

        <div class="mt-2.5 rounded-lg border border-slate-200/90 bg-white p-2.5 sm:p-3">
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                Đối tượng áp dụng
              </p>
              <p class="text-[11px] text-slate-400">
                Chọn theo chức danh hoặc cấp bậc — không chọn cả hai.
              </p>
            </div>
            <div
              class="inline-flex shrink-0 rounded-lg border border-slate-200 bg-slate-50 p-0.5"
              role="group"
              aria-label="Kiểu đối tượng áp dụng"
            >
              <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition"
                :class="targetMode === 'title'
                  ? 'bg-white text-brand shadow-sm ring-1 ring-slate-200'
                  : 'text-slate-500 hover:text-slate-700'"
                @click="setTargetMode('title')"
              >
                <AppIcon
                  name="briefcase"
                  :size="13"
                />
                Chức danh
                <span
                  v-if="form.titles.length"
                  class="rounded bg-brand/10 px-1 py-px text-[10px] font-semibold tabular-nums text-brand"
                >{{ form.titles.length }}</span>
              </button>
              <button
                type="button"
                class="inline-flex h-8 items-center gap-1.5 rounded-md px-2.5 text-xs font-medium transition"
                :class="targetMode === 'rank'
                  ? 'bg-white text-brand shadow-sm ring-1 ring-slate-200'
                  : 'text-slate-500 hover:text-slate-700'"
                @click="setTargetMode('rank')"
              >
                <AppIcon
                  name="award"
                  :size="13"
                />
                Cấp bậc
                <span
                  v-if="form.ranks.length"
                  class="rounded bg-brand/10 px-1 py-px text-[10px] font-semibold tabular-nums text-brand"
                >{{ form.ranks.length }}</span>
              </button>
            </div>
          </div>

          <MultiCatalogSelect
            v-if="targetMode === 'title'"
            v-model="titlesModel"
            :options="jobTitles"
            placeholder="Tìm và chọn chức danh…"
          />
          <MultiCatalogSelect
            v-else
            v-model="ranksModel"
            :options="jobRanks"
            placeholder="Tìm và chọn cấp bậc…"
          />
          <p
            v-if="form.errors.titles || form.errors.ranks"
            class="mt-1.5 text-xs text-rose-600"
          >
            {{ form.errors.titles || form.errors.ranks }}
          </p>
        </div>
      </section>

      <!-- 2. Tiêu chí -->
      <section class="overflow-hidden rounded-xl border border-slate-200">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-3.5 py-2.5">
          <div class="flex min-w-0 items-center gap-2">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-emerald-50 text-emerald-600">
              <AppIcon
                name="action-items"
                :size="13"
              />
            </span>
            <h3 class="text-sm font-semibold text-slate-800">
              Tiêu chí đánh giá
            </h3>
            <span
              v-if="form.criteria.length"
              class="rounded-md bg-white px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-slate-500 ring-1 ring-slate-200"
            >
              {{ form.criteria.length }}
            </span>
          </div>
          <div class="flex items-center gap-2">
            <div
              class="rounded-md px-2 py-0.5 text-[11px] font-semibold tabular-nums"
              :class="weightSumOk
                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                : form.criteria.length
                  ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
                  : 'bg-white text-slate-500 ring-1 ring-slate-200'"
              :title="weightSumOk ? 'Tổng trọng số đủ 100%' : 'Nên phân bổ đủ 100%'"
            >
              Tổng {{ weightSum }}%
            </div>
            <button
              type="button"
              class="btn-ghost inline-flex h-8 items-center gap-1 px-2 text-xs"
              @click="addCustomCriterion"
            >
              <AppIcon
                name="plus"
                :size="13"
              />
              Tuỳ chỉnh
            </button>
          </div>
        </div>

        <div class="grid gap-0 lg:grid-cols-12">
          <!-- Catalog picker (hẹp hơn) -->
          <div class="border-b border-slate-100 lg:col-span-3 lg:border-b-0 lg:border-r">
            <div class="flex items-center gap-2 border-b border-slate-100 px-2.5 py-2">
              <AppIcon
                name="search"
                :size="13"
                class="shrink-0 text-slate-400"
              />
              <input
                v-model="criteriaQuery"
                type="search"
                class="h-7 w-full border-0 bg-transparent text-xs outline-none placeholder:text-slate-400"
                placeholder="Tìm danh mục…"
              >
              <span class="shrink-0 text-[10px] font-semibold tabular-nums text-slate-400">
                {{ filteredCatalog.length }}
              </span>
            </div>
            <ul class="max-h-44 overflow-y-auto bg-white lg:max-h-[22rem]">
              <li
                v-for="item in filteredCatalog"
                :key="item.id"
                class="flex cursor-pointer items-center gap-2 px-2.5 py-1.5 text-xs transition-colors hover:bg-slate-50"
                :class="selectedCatalogIds.has(item.id) ? 'bg-brand/[0.04]' : ''"
                @click="toggleCatalog(item)"
              >
                <span
                  class="flex h-4 w-4 shrink-0 items-center justify-center rounded border"
                  :class="selectedCatalogIds.has(item.id)
                    ? 'border-brand bg-brand text-white'
                    : 'border-slate-300 bg-white'"
                >
                  <AppIcon
                    v-if="selectedCatalogIds.has(item.id)"
                    name="check"
                    :size="10"
                  />
                </span>
                <span class="min-w-0 flex-1">
                  <span class="block truncate font-medium text-slate-800">{{ item.criteria_name }}</span>
                  <span class="block truncate text-[10px] text-slate-400">
                    {{ item.criteria_code }} · {{ item.category || 'Khác' }}
                  </span>
                </span>
              </li>
              <li
                v-if="!filteredCatalog.length"
                class="px-3 py-5 text-center text-xs text-slate-400"
              >
                Không tìm thấy tiêu chí
              </li>
            </ul>
          </div>

          <!-- Selected criteria (rộng hơn) -->
          <div class="min-w-0 bg-slate-50/40 lg:col-span-9">
            <div
              v-if="form.criteria.length"
              class="space-y-2.5 p-3"
            >
              <article
                v-for="(c, idx) in form.criteria"
                :key="c.source === 'catalog' ? `c-${c.criterion_id}` : `x-${idx}`"
                class="group relative overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm ring-1 ring-black/[0.02] transition-shadow hover:border-slate-300 hover:shadow-md"
              >
                <div class="flex items-start gap-3 px-2.5 py-3 sm:px-3">
                  <span
                    class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-[11px] font-bold tabular-nums"
                    :class="c.source === 'catalog'
                      ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-100'
                      : 'bg-violet-50 text-violet-700 ring-1 ring-violet-100'"
                  >
                    {{ idx + 1 }}
                  </span>

                  <div class="min-w-0 flex-1 space-y-2.5">
                    <div class="flex min-w-0 flex-wrap items-start justify-between gap-2">
                      <div class="min-w-0 flex-1">
                        <template v-if="c.source === 'catalog'">
                          <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <h4 class="truncate text-sm font-semibold text-slate-900">
                              {{ c.criteria_name }}
                            </h4>
                            <span class="inline-flex items-center rounded-md bg-sky-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-700 ring-1 ring-sky-100">
                              Danh mục
                            </span>
                          </div>
                          <p class="mt-0.5 font-mono text-[11px] text-slate-400">
                            {{ c.criteria_code }}
                          </p>
                        </template>
                        <template v-else>
                          <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <input
                              v-model="c.custom_name"
                              type="text"
                              class="input h-8 min-w-[10rem] flex-1 text-sm font-medium"
                              placeholder="Tên tiêu chí *"
                              required
                            >
                            <input
                              v-model="c.custom_category"
                              type="text"
                              class="input h-8 w-28 text-xs"
                              placeholder="Nhóm"
                            >
                            <span class="inline-flex items-center rounded-md bg-violet-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-violet-700 ring-1 ring-violet-100">
                              Tuỳ chỉnh
                            </span>
                          </div>
                        </template>
                      </div>

                      <div class="flex shrink-0 items-center gap-0.5 opacity-70 transition-opacity group-hover:opacity-100">
                        <button
                          type="button"
                          class="btn-ghost h-7 w-7 p-0"
                          title="Đưa lên"
                          :disabled="idx === 0"
                          @click="moveCriterion(idx, -1)"
                        >
                          <AppIcon
                            name="chevron-up"
                            :size="13"
                          />
                        </button>
                        <button
                          type="button"
                          class="btn-ghost h-7 w-7 p-0"
                          title="Đưa xuống"
                          :disabled="idx === form.criteria.length - 1"
                          @click="moveCriterion(idx, 1)"
                        >
                          <AppIcon
                            name="chevron-down"
                            :size="13"
                          />
                        </button>
                        <button
                          type="button"
                          class="btn-ghost h-7 w-7 p-0 text-rose-600"
                          title="Xóa"
                          @click="removeCriterion(idx)"
                        >
                          <AppIcon
                            name="trash"
                            :size="13"
                          />
                        </button>
                      </div>
                    </div>

                    <div class="grid w-full grid-cols-1 gap-2 sm:grid-cols-12">
                      <label class="block min-w-0 sm:col-span-2">
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                          Trọng số
                        </span>
                        <select
                          v-model.number="c.weight"
                          class="input h-9 w-full cursor-pointer text-xs font-semibold tabular-nums"
                          title="Trọng số"
                        >
                          <option
                            v-for="pct in weightSelectOptions(c.weight)"
                            :key="`w-${idx}-${pct}`"
                            :value="pct"
                          >
                            {{ pct }}%
                          </option>
                        </select>
                      </label>

                      <div class="min-w-0 sm:col-span-7">
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                          Điểm yêu cầu
                        </span>
                        <div
                          v-if="c.source === 'catalog' && scoreLevelsFor(c).length"
                          class="flex w-full min-w-0 items-center gap-1.5"
                        >
                          <select
                            v-model="c.required_score_label"
                            class="input h-9 min-w-0 flex-1 cursor-pointer text-xs"
                            title="Điểm yêu cầu"
                          >
                            <option value="">
                              Chọn mức điểm
                            </option>
                            <option
                              v-if="c.required_score_label
                                && !scoreLevelsFor(c).some((l) => l.label === c.required_score_label)"
                              :value="c.required_score_label"
                            >
                              {{ c.required_score_label }}
                            </option>
                            <option
                              v-for="level in scoreLevelsFor(c)"
                              :key="`${c.criterion_id}-${level.code || level.label}`"
                              :value="level.label"
                            >
                              {{ level.label }}{{ formatLevelPoints(level) ? ` · ${formatLevelPoints(level)}đ` : '' }}
                            </option>
                          </select>
                          <span
                            v-if="formatLevelPoints(selectedScoreLevel(c))"
                            class="inline-flex h-9 shrink-0 items-center rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 text-[11px] font-bold tabular-nums text-emerald-700"
                          >
                            {{ formatLevelPoints(selectedScoreLevel(c)) }}đ
                          </span>
                        </div>
                        <input
                          v-else
                          v-model="c.required_score_label"
                          type="text"
                          class="input h-9 w-full text-xs"
                          placeholder="Nhập điểm yêu cầu"
                        >
                      </div>

                      <label
                        class="flex w-full cursor-pointer flex-col justify-end sm:col-span-3"
                        title="Tính vào tổng điểm"
                      >
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                          Tổng điểm
                        </span>
                        <span
                          class="inline-flex h-9 w-full items-center justify-center gap-1.5 rounded-lg border px-2.5 text-[11px] font-semibold transition-colors"
                          :class="c.include_in_total
                            ? 'border-brand/30 bg-brand/[0.06] text-brand'
                            : 'border-slate-200 bg-slate-50 text-slate-500'"
                        >
                          <input
                            v-model="c.include_in_total"
                            type="checkbox"
                            class="rounded border-slate-300 text-brand focus:ring-brand"
                          >
                          {{ c.include_in_total ? 'Có tính' : 'Không' }}
                        </span>
                      </label>
                    </div>
                  </div>
                </div>
              </article>
            </div>
            <div
              v-else
              class="flex h-full min-h-[8rem] flex-col items-center justify-center px-4 py-6 text-center"
            >
              <p class="text-xs font-medium text-slate-500">
                Chưa chọn tiêu chí
              </p>
              <p class="mt-1 text-[11px] text-slate-400">
                Tick bên trái hoặc thêm tiêu chí tuỳ chỉnh.
              </p>
            </div>
          </div>
        </div>
        <p
          v-if="form.errors.criteria"
          class="border-t border-slate-100 px-3.5 py-2 text-xs text-rose-600"
        >
          {{ form.errors.criteria }}
        </p>
      </section>

      <!-- 3. Trường bổ sung -->
      <section class="overflow-hidden rounded-xl border border-slate-200">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 px-3.5 py-2.5">
          <div class="flex min-w-0 items-center gap-2">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-violet-50 text-violet-600">
              <AppIcon
                name="list"
                :size="13"
              />
            </span>
            <div class="min-w-0">
              <h3 class="text-sm font-semibold text-slate-800">
                Trường bổ sung
              </h3>
              <p class="text-[10px] leading-snug text-slate-400">
                Thông tin thêm trên phiếu · không tính điểm
              </p>
            </div>
            <span
              v-if="form.fields.length"
              class="rounded-md bg-white px-1.5 py-0.5 text-[10px] font-semibold tabular-nums text-slate-500 ring-1 ring-slate-200"
            >
              {{ form.fields.length }}
            </span>
          </div>
          <button
            type="button"
            class="btn-ghost inline-flex h-8 items-center gap-1 px-2 text-xs"
            @click="addField"
          >
            <AppIcon
              name="plus"
              :size="13"
            />
            Thêm
          </button>
        </div>

        <div
          v-if="form.fields.length"
          class="divide-y divide-slate-100"
        >
          <div
            v-for="(f, idx) in form.fields"
            :key="f.field_key"
            class="px-3.5 py-2.5"
          >
            <div class="flex items-start gap-2">
              <span class="mt-1 flex h-5 w-5 shrink-0 items-center justify-center rounded bg-violet-50 text-[10px] font-semibold tabular-nums text-violet-700">
                {{ idx + 1 }}
              </span>
              <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                  <input
                    v-model="f.label"
                    type="text"
                    class="input h-8 min-w-[12rem] flex-1 text-sm"
                    placeholder="Nội dung hỏi trên phiếu *"
                    required
                  >
                  <select
                    v-model="f.field_type"
                    class="input h-8 w-36 cursor-pointer text-xs"
                    :title="fieldTypeLabel(f.field_type)"
                  >
                    <option
                      v-for="opt in fieldTypeOptions"
                      :key="opt.value"
                      :value="opt.value"
                    >
                      {{ opt.label }}
                    </option>
                  </select>
                  <label class="inline-flex h-8 cursor-pointer items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2 text-[11px] text-slate-600">
                    <input
                      v-model="f.is_required"
                      type="checkbox"
                      class="rounded border-slate-300 text-brand focus:ring-brand"
                    >
                    Bắt buộc
                  </label>
                </div>

                <textarea
                  v-if="needsOptions(f.field_type)"
                  v-model="f.optionsText"
                  rows="2"
                  class="input w-full text-xs"
                  :placeholder="SELECT_OPTIONS_PLACEHOLDER"
                />

                <div
                  v-if="fieldExtrasOpen[f.field_key]"
                  class="grid gap-2 sm:grid-cols-2"
                >
                  <input
                    v-if="needsPlaceholder(f.field_type)"
                    v-model="f.placeholder"
                    type="text"
                    class="input h-8 w-full text-xs"
                    placeholder="Gợi ý trong ô (tuỳ chọn)"
                  >
                  <input
                    v-model="f.help_text"
                    type="text"
                    class="input h-8 w-full text-xs"
                    placeholder="Hướng dẫn dưới ô (tuỳ chọn)"
                  >
                </div>

                <button
                  type="button"
                  class="text-[11px] font-medium text-slate-500 hover:text-brand"
                  @click="toggleFieldExtras(f.field_key)"
                >
                  {{ fieldExtrasOpen[f.field_key] ? 'Ẩn gợi ý / hướng dẫn' : 'Thêm gợi ý / hướng dẫn' }}
                </button>
              </div>

              <div class="flex shrink-0 items-center gap-0.5">
                <button
                  type="button"
                  class="btn-ghost h-7 w-7 p-0"
                  title="Đưa lên"
                  :disabled="idx === 0"
                  @click="moveField(idx, -1)"
                >
                  <AppIcon
                    name="chevron-up"
                    :size="13"
                  />
                </button>
                <button
                  type="button"
                  class="btn-ghost h-7 w-7 p-0"
                  title="Đưa xuống"
                  :disabled="idx === form.fields.length - 1"
                  @click="moveField(idx, 1)"
                >
                  <AppIcon
                    name="chevron-down"
                    :size="13"
                  />
                </button>
                <button
                  type="button"
                  class="btn-ghost h-7 w-7 p-0 text-rose-600"
                  title="Xóa"
                  @click="removeField(idx)"
                >
                  <AppIcon
                    name="trash"
                    :size="13"
                  />
                </button>
              </div>
            </div>
          </div>
        </div>
        <div
          v-else
          class="px-3.5 py-5 text-center"
        >
          <p class="text-xs text-slate-400">
            Không bắt buộc — thêm khi phiếu cần thu thập thông tin ngoài tiêu chí.
          </p>
        </div>
        <p
          v-if="form.errors.fields"
          class="border-t border-slate-100 px-3.5 py-2 text-xs text-rose-600"
        >
          {{ form.errors.fields }}
        </p>
      </section>
    </form>

    <div
      class="flex justify-end gap-2"
      :class="isPage
        ? 'border-t border-slate-100 bg-slate-50/50 px-4 py-3 sm:px-5'
        : 'pt-2'"
    >
      <button
        type="button"
        class="btn-ghost h-9 px-3 text-sm"
        @click="requestClose"
      >
        Huỷ
      </button>
      <button
        type="button"
        class="btn-primary inline-flex h-9 items-center gap-1.5 px-4 text-sm"
        :disabled="form.processing"
        @click="submit"
      >
        <AppIcon
          v-if="form.processing"
          name="refresh"
          :size="14"
          class="animate-spin"
        />
        {{ isEdit ? 'Lưu thay đổi' : 'Tạo mẫu' }}
      </button>
    </div>
  </div>
</template>
