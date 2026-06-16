<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useCredentialPassword } from '@/modules/credential/composables/useCredentialPassword';

const props = defineProps({
    credentialId: { type: Number, required: true },
    canViewPassword: { type: Boolean, default: false },
    hasPassword: { type: Boolean, default: false },
    passwordChangedAt: { type: String, default: null },
    passwordExpiresAt: { type: String, default: null },
    mfaEnabled: { type: Boolean, default: false },
});

const { visible, password, loading, reveal, copy, hide } = useCredentialPassword(
    props.credentialId,
    () => props.canViewPassword,
);

const masked = computed(() => (props.hasPassword ? '••••••••••••' : '—'));
</script>

<template>
  <div class="rounded-xl border border-slate-200/80 bg-white p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Mật khẩu
        </p>
        <p class="mt-2 font-mono text-lg tabular-nums text-slate-800">
          {{ visible ? password : masked }}
        </p>
        <p
          v-if="!hasPassword"
          class="mt-1 text-xs text-slate-500"
        >
          Chưa lưu mật khẩu
        </p>
      </div>
      <div
        v-if="canViewPassword && hasPassword"
        class="flex flex-wrap gap-2"
      >
        <button
          type="button"
          class="btn-ghost h-9 gap-1.5 px-3 text-xs"
          :disabled="loading"
          @click="visible ? hide() : reveal('view')"
        >
          <AppIcon
            :name="visible ? 'eye-off' : 'eye'"
            :size="15"
          />
          {{ visible ? 'Ẩn' : 'Hiện' }}
        </button>
        <button
          type="button"
          class="btn-ghost h-9 gap-1.5 px-3 text-xs"
          :disabled="loading"
          @click="copy()"
        >
          <AppIcon
            name="copy"
            :size="15"
          />
          Sao chép
        </button>
      </div>
    </div>
    <dl class="mt-4 grid gap-3 sm:grid-cols-2">
      <div>
        <dt class="text-xs text-slate-500">
          Đổi mật khẩu gần nhất
        </dt>
        <dd class="text-sm font-medium text-slate-800">
          {{ passwordChangedAt || '—' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs text-slate-500">
          Hết hạn mật khẩu
        </dt>
        <dd class="text-sm font-medium text-slate-800">
          {{ passwordExpiresAt || '—' }}
        </dd>
      </div>
      <div>
        <dt class="text-xs text-slate-500">
          MFA / 2FA
        </dt>
        <dd
          class="text-sm font-medium"
          :class="mfaEnabled ? 'text-emerald-700' : 'text-amber-700'"
        >
          {{ mfaEnabled ? 'Đã bật' : 'Chưa bật' }}
        </dd>
      </div>
    </dl>
  </div>
</template>
