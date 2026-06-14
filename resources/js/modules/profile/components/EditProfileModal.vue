<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Ui/Modal.vue';
import FormField from '@/shared/ui/form/FormField.vue';
import TextInput from '@/shared/ui/form/TextInput.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    profile: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const socials = props.profile.socials || {};

const form = useForm({
    _method: 'put',
    phone: props.profile.phone || '',
    role_title: props.profile.role_title || '',
    bio: props.profile.bio || '',
    location: props.profile.location || '',
    github: socials.github || '',
    linkedin: socials.linkedin || '',
    portfolio: socials.portfolio || '',
    website: socials.website || '',
    skills: [...(props.profile.skills?.groups?.flatMap((g) => g.items.map((i) => i.name)) || [])],
    avatar: null,
});

// --- Skills tag input ---
const skillDraft = ref('');
function addSkill() {
    const v = skillDraft.value.trim();
    if (v && !form.skills.includes(v) && form.skills.length < 40) {
        form.skills.push(v);
    }
    skillDraft.value = '';
}
function removeSkill(i) {
    form.skills.splice(i, 1);
}

// --- Avatar preview ---
const avatarPreview = ref(null);
function onAvatarChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    form.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
}

const previewSrc = computed(() => avatarPreview.value || props.profile.avatar_path);

function submit() {
    form.post('/profile', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('avatar');
            avatarPreview.value = null;
            emit('close');
        },
    });
}
</script>

<template>
  <Modal
    :show="show"
    title="Chỉnh sửa hồ sơ"
    max-width="max-w-2xl"
    :dirty="form.isDirty"
    @close="emit('close')"
  >
    <form
      class="space-y-5"
      @submit.prevent="submit"
    >
      <!-- Avatar -->
      <div class="flex items-center gap-4">
        <Avatar
          :name="profile.name"
          :src="previewSrc"
          :size="64"
        />
        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 text-[13px] font-medium text-slate-700 transition-colors hover:bg-slate-50">
          <AppIcon
            name="upload"
            :size="15"
          />
          Đổi ảnh đại diện
          <input
            type="file"
            accept="image/png,image/jpeg,image/webp"
            class="hidden"
            @change="onAvatarChange"
          >
        </label>
      </div>
      <p
        v-if="form.errors.avatar"
        class="-mt-3 text-xs text-danger"
      >
        {{ form.errors.avatar }}
      </p>

      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField
          label="Chức danh"
          :error="form.errors.role_title"
        >
          <TextInput
            v-model="form.role_title"
            placeholder="VD: Senior Laravel Developer"
            :invalid="!!form.errors.role_title"
          />
        </FormField>
        <FormField
          label="Điện thoại"
          :error="form.errors.phone"
        >
          <TextInput
            v-model="form.phone"
            placeholder="VD: 0901 234 567"
            :invalid="!!form.errors.phone"
          />
        </FormField>
      </div>

      <FormField
        label="Giới thiệu"
        :error="form.errors.bio"
        hint="Tối đa 500 ký tự"
      >
        <textarea
          v-model="form.bio"
          rows="3"
          class="input w-full"
          placeholder="Một vài dòng giới thiệu về bản thân, thế mạnh, lĩnh vực..."
        />
      </FormField>

      <FormField
        label="Địa điểm"
        :error="form.errors.location"
      >
        <TextInput
          v-model="form.location"
          placeholder="VD: Hà Nội, Việt Nam"
          :invalid="!!form.errors.location"
        />
      </FormField>

      <!-- Skills -->
      <FormField
        label="Kỹ năng"
        :error="form.errors.skills"
        hint="Nhấn Enter để thêm kỹ năng"
      >
        <div class="input flex min-h-[42px] w-full flex-wrap items-center gap-1.5">
          <span
            v-for="(s, i) in form.skills"
            :key="s + i"
            class="inline-flex items-center gap-1 rounded-md bg-brand/5 px-2 py-0.5 text-[12.5px] font-medium text-brand"
          >
            {{ s }}
            <button
              type="button"
              class="text-brand/50 hover:text-brand"
              @click="removeSkill(i)"
            >
              <AppIcon
                name="close"
                :size="12"
              />
            </button>
          </span>
          <input
            v-model="skillDraft"
            type="text"
            class="min-w-[120px] flex-1 border-0 bg-transparent p-0 text-[13px] focus:outline-none focus:ring-0"
            placeholder="Thêm kỹ năng..."
            @keydown.enter.prevent="addSkill"
            @keydown.,.prevent="addSkill"
          >
        </div>
      </FormField>

      <!-- Socials -->
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <FormField
          label="GitHub"
          :error="form.errors.github"
        >
          <TextInput
            v-model="form.github"
            type="url"
            placeholder="https://github.com/..."
            :invalid="!!form.errors.github"
          />
        </FormField>
        <FormField
          label="LinkedIn"
          :error="form.errors.linkedin"
        >
          <TextInput
            v-model="form.linkedin"
            type="url"
            placeholder="https://linkedin.com/in/..."
            :invalid="!!form.errors.linkedin"
          />
        </FormField>
        <FormField
          label="Portfolio"
          :error="form.errors.portfolio"
        >
          <TextInput
            v-model="form.portfolio"
            type="url"
            placeholder="https://..."
            :invalid="!!form.errors.portfolio"
          />
        </FormField>
        <FormField
          label="Website"
          :error="form.errors.website"
        >
          <TextInput
            v-model="form.website"
            type="url"
            placeholder="https://..."
            :invalid="!!form.errors.website"
          />
        </FormField>
      </div>

      <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="rounded-lg px-4 py-2 text-[13px] font-medium text-slate-600 hover:bg-slate-100"
          @click="emit('close')"
        >
          Huỷ
        </button>
        <button
          type="submit"
          class="btn-primary"
          :disabled="form.processing"
        >
          {{ form.processing ? 'Đang lưu...' : 'Lưu thay đổi' }}
        </button>
      </div>
    </form>
  </Modal>
</template>
