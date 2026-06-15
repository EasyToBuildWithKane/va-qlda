<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { useCountUp } from '@/shared/composables/useCountUp.js';

const props = defineProps({
    profile: { type: Object, required: true },
    editable: { type: Boolean, default: false },
});

defineEmits(['edit']);

const stats = computed(() => props.profile.stats ?? {});
const leadTeam = computed(() => props.profile.teams?.find((t) => t.is_leader) ?? null);
const primaryTeam = computed(() => props.profile.teams?.[0] ?? null);

const hasSkillScore = computed(() => stats.value.skill_score != null);

const completionUp = useCountUp(() => stats.value.profile_completion ?? 0);
const skillUp = useCountUp(() => stats.value.skill_score ?? 0);
</script>

<template>
  <section class="profile-hero">
    <!-- Cover -->
    <div class="profile-hero__cover">
      <div
        class="profile-hero__cover-grid"
        aria-hidden="true"
      />
      <div
        class="profile-hero__cover-orb"
        aria-hidden="true"
      />
    </div>

    <div class="px-5 pb-5 sm:px-7">
      <div class="-mt-12 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex items-end gap-4">
          <div class="rounded-full bg-white p-1 shadow-md ring-1 ring-slate-200/70">
            <Avatar
              :name="profile.name"
              :src="profile.avatar_path"
              :size="96"
            />
          </div>
          <div class="pb-1">
            <div class="flex flex-wrap items-center gap-2">
              <h1 class="font-display text-xl font-semibold leading-tight text-slate-900">
                {{ profile.name }}
              </h1>
              <Badge
                :label="profile.seniority.label"
                :color="profile.seniority.color"
              />
            </div>
            <p class="mt-1 text-sm text-slate-500">
              {{ profile.role_title || 'Chưa cập nhật chức danh' }}
            </p>
          </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
          <button
            v-if="editable"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-[13px] font-medium text-slate-700 transition-colors hover:bg-slate-50"
            @click="$emit('edit')"
          >
            <AppIcon
              name="edit"
              :size="15"
            />
            Chỉnh sửa hồ sơ
          </button>
        </div>
      </div>

      <!-- Identity chips -->
      <div class="mt-4 flex flex-wrap items-center gap-2 text-[12px]">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600">
          <AppIcon
            name="account"
            :size="13"
            class="text-slate-400"
          />
          {{ profile.code }}
        </span>
        <Badge
          v-if="profile.account_role"
          :label="profile.account_role.label"
          :color="profile.account_role.color"
        />
        <span
          v-if="primaryTeam"
          class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-600"
        >
          <AppIcon
            name="org-teams"
            :size="13"
            class="text-slate-400"
          />
          {{ primaryTeam.name }}{{ leadTeam ? ' · Trưởng nhóm' : '' }}
        </span>
        <span
          class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-medium"
          :class="profile.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'"
        >
          <span
            class="h-1.5 w-1.5 rounded-full"
            :class="profile.is_active ? 'bg-emerald-500' : 'bg-slate-400'"
          />
          {{ profile.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động' }}
        </span>
      </div>

      <!-- Mini analytics -->
      <div class="mt-5 grid grid-cols-2 gap-3 border-t border-slate-100 pt-5 sm:grid-cols-4">
        <div class="profile-hero__stat">
          <div class="profile-hero__stat-head">
            <span class="profile-hero__stat-label">Hoàn thiện hồ sơ</span>
            <AppIcon
              name="target"
              :size="15"
              class="text-brand/70"
            />
          </div>
          <p class="profile-hero__stat-value">
            {{ completionUp.display.value }}<span class="text-base text-slate-400">%</span>
          </p>
          <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100">
            <div
              class="h-full rounded-full bg-brand transition-[width] duration-700 ease-out"
              :style="{ width: `${stats.profile_completion ?? 0}%` }"
            />
          </div>
        </div>

        <div class="profile-hero__stat">
          <div class="profile-hero__stat-head">
            <span class="profile-hero__stat-label">Cấp bậc</span>
            <AppIcon
              name="career"
              :size="15"
              class="text-violet-500/80"
            />
          </div>
          <p class="profile-hero__stat-value profile-hero__stat-value--text">
            {{ profile.seniority.label }}
          </p>
          <p class="profile-hero__stat-sub">
            Cấp độ năng lực
          </p>
        </div>

        <div class="profile-hero__stat">
          <div class="profile-hero__stat-head">
            <span class="profile-hero__stat-label">Điểm kỹ năng</span>
            <AppIcon
              name="talent-score"
              :size="15"
              class="text-amber-500/80"
            />
          </div>
          <p class="profile-hero__stat-value">
            <template v-if="hasSkillScore">
              {{ skillUp.display.value }}<span class="text-base text-slate-400">/100</span>
            </template>
            <span
              v-else
              class="text-slate-300"
            >—</span>
          </p>
          <div
            v-if="hasSkillScore"
            class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100"
          >
            <div
              class="h-full rounded-full bg-amber-500 transition-[width] duration-700 ease-out"
              :style="{ width: `${stats.skill_score ?? 0}%` }"
            />
          </div>
          <p
            v-else
            class="profile-hero__stat-sub"
          >
            Chưa chấm mức độ kỹ năng
          </p>
        </div>

        <div class="profile-hero__stat">
          <div class="profile-hero__stat-head">
            <span class="profile-hero__stat-label">Thâm niên</span>
            <AppIcon
              name="clock"
              :size="15"
              class="text-sky-500/80"
            />
          </div>
          <p class="profile-hero__stat-value profile-hero__stat-value--text">
            {{ stats.tenure?.label ?? '—' }}
          </p>
          <p class="profile-hero__stat-sub">
            Tại tổ chức
          </p>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.profile-hero {
    overflow: hidden;
    border-radius: 18px;
    border: 1px solid rgba(226, 232, 240, 0.8);
    background: #fff;
    box-shadow: 0 10px 30px -22px rgba(15, 23, 42, 0.5);
}

.profile-hero__cover {
    position: relative;
    height: 7rem;
    overflow: hidden;
    background: linear-gradient(120deg, #9a0036 0%, #b81350 55%, #d6418a 100%);
}

.profile-hero__cover-grid {
    position: absolute;
    inset: 0;
    background-image:
        linear-gradient(rgba(255, 255, 255, 0.12) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.12) 1px, transparent 1px);
    background-size: 26px 26px;
    mask-image: radial-gradient(120% 100% at 80% 0%, #000, transparent 75%);
}

.profile-hero__cover-orb {
    position: absolute;
    top: -40%;
    right: 8%;
    width: 220px;
    height: 220px;
    border-radius: 9999px;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.28), transparent 70%);
}

.profile-hero__stat {
    border-radius: 14px;
    border: 1px solid rgba(226, 232, 240, 0.9);
    background: linear-gradient(180deg, #fff, #fafbfc);
    padding: 0.7rem 0.8rem;
    transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.25s ease;
}

.profile-hero__stat:hover {
    transform: translateY(-2px);
    border-color: rgba(154, 0, 54, 0.28);
    box-shadow: 0 12px 26px -20px rgba(154, 0, 54, 0.45);
}

.profile-hero__stat-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.4rem;
}

.profile-hero__stat-label {
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #94a3b8;
}

.profile-hero__stat-value {
    margin-top: 0.35rem;
    font-family: var(--font-display, inherit);
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1;
    letter-spacing: -0.01em;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.profile-hero__stat-value--text {
    font-size: 1.05rem;
}

.profile-hero__stat-sub {
    margin-top: 0.4rem;
    font-size: 11px;
    color: #94a3b8;
}
</style>
