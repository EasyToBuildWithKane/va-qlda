<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import CredentialFieldLabel from '@/modules/credential/components/CredentialFieldLabel.vue';
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

const masked = computed(() => (props.hasPassword ? '••••••••••••' : null));

function displayMeta(value, empty = 'Chưa cập nhật') {
    if (value === null || value === undefined || value === '') return empty;
    return value;
}
</script>

<template>
  <div class="card p-5">
    <h3 class="mb-4 text-xs font-semibold uppercase tracking-wide text-slate-500">
      Mật khẩu & xác thực
    </h3>
    <div class="grid gap-5 lg:grid-cols-2">
      <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4">
        <CredentialFieldLabel
          label="Mật khẩu đăng nhập"
          tooltip="Bấm Hiện hoặc Sao chép chỉ khi bạn có quyền. Mọi thao tác được ghi nhật ký."
          wide
        />
        <p
          v-if="hasPassword"
          class="mt-2 font-mono text-lg tabular-nums text-slate-800"
        >
          {{ visible ? password : masked }}
        </p>
        <p
          v-else
          class="mt-2 text-sm text-slate-500"
        >
          Chưa lưu mật khẩu
        </p>
        <div
          v-if="canViewPassword && hasPassword"
          class="mt-3 flex flex-wrap gap-2"
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

      <dl class="grid gap-4 sm:grid-cols-1">
        <div class="rounded-xl border border-slate-100 px-4 py-3">
          <CredentialFieldLabel
            label="Đổi mật khẩu gần nhất"
            tooltip="Thời điểm cập nhật secret lần cuối trên hệ thống."
          />
          <dd class="mt-1 text-sm font-medium text-slate-800">
            {{ displayMeta(passwordChangedAt) }}
          </dd>
        </div>
        <div class="rounded-xl border border-slate-100 px-4 py-3">
          <CredentialFieldLabel
            label="Hết hạn mật khẩu"
            tooltip="Ngày hết hạn chính sách mật khẩu (nếu được thiết lập)."
          />
          <dd class="mt-1 text-sm font-medium text-slate-800">
            {{ displayMeta(passwordExpiresAt, 'Không hết hạn') }}
          </dd>
        </div>
        <div class="rounded-xl border border-slate-100 px-4 py-3">
          <CredentialFieldLabel
            label="MFA / 2FA"
            tooltip="Trạng thái xác thực hai lớp ghi nhận trên hồ sơ."
          />
          <dd
            class="mt-1 text-sm font-medium"
            :class="mfaEnabled ? 'text-emerald-700' : 'text-amber-700'"
          >
            {{ mfaEnabled ? 'Đã bật' : 'Chưa bật' }}
          </dd>
        </div>
      </dl>
    </div>
  </div>
</template>
