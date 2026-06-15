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
    skills: (props.profile.skills?.groups || []).flatMap((g) =>
        g.items.map((i) => ({ name: i.name, level: i.level ?? 3 })),
    ),
    avatar: null,
});

const levels = [1, 2, 3, 4, 5];

function addSkill() {
    if (form.skills.length < 40) form.skills.push({ name: '', level: 3 });
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
    form
        .transform((data) => ({
            ...data,
            skills: data.skills.filter((s) => s.name.trim() !== ''),
        }))
        .post('/profile', {
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
      class="max-h-[70vh] space-y-5 overflow-y-auto pr-1"
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

      <!-- Skills with level -->
      <div>
        <div class="mb-2 flex items-center justify-between">
          <label class="block text-sm font-medium text-slate-700">Kỹ năng & mức độ</label>
          <button
            type="button"
            class="inline-flex items-center gap-1 text-[12.5px] font-medium text-brand hover:text-brand/80"
            @click="addSkill"
          >
            <AppIcon
              name="add"
              :size="13"
            /> Thêm kỹ năng
          </button>
        </div>
        <div class="space-y-2">
          <div
            v-for="(s, i) in form.skills"
            :key="i"
            class="flex items-center gap-2"
          >
            <input
              v-model="s.name"
              type="text"
              class="input flex-1"
              placeholder="VD: Laravel"
            >
            <select
              v-model.number="s.level"
              class="input w-28 shrink-0"
            >
              <option
                v-for="n in levels"
                :key="n"
                :value="n"
              >
                Cấp {{ n }}/5
              </option>
            </select>
            <button
              type="button"
              class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-500"
              @click="form.skills.splice(i, 1)"
            >
              <AppIcon
                name="delete"
                :size="15"
              />
            </button>
          </div>
          <p
            v-if="!form.skills.length"
            class="text-[12.5px] text-slate-400"
          >
            Chưa có kỹ năng nào.
          </p>
        </div>
      </div>

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
