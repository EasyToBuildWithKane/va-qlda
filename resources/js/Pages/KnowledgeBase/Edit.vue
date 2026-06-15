<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import KbRichTextField from '@/Components/KnowledgeBase/KbRichTextField.vue';
import KbImageGallery from '@/Components/KnowledgeBase/KbImageGallery.vue';
import KbTagField from '@/Components/KnowledgeBase/KbTagField.vue';
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';
import Badge from '@/shared/ui/Badge.vue';
import RichContentBody from '@/shared/ui/RichContentBody.vue';
import { slugifyTitle } from '@/shared/utils/slugify';
import { useToast } from '@/shared/composables/useToast';

const toast = useToast();

const props = defineProps({
    article: { type: Object, default: null },
    categories: { type: Object, required: true },
    tagSuggestions: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({}) },
});

const isEdit = computed(() => Boolean(props.article?.id));
const previewMode = ref(false);
const bootstrappedSlug = ref(null);
const bootstrapInFlight = ref(false);

const persistedSlug = computed(() => props.article?.slug ?? bootstrappedSlug.value ?? '');

const imageUploadUrl = computed(() => (
    persistedSlug.value
        ? route('knowledge-base.articles.images.store', persistedSlug.value)
        : ''
));

async function resolveImageUploadUrl() {
    if (imageUploadUrl.value) return imageUploadUrl.value;
    if (bootstrapInFlight.value) return '';
    if (!form.category_id) {
        toast.error('Chọn danh mục trước khi chèn ảnh.');
        return '';
    }
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    bootstrapInFlight.value = true;
    try {
        const title = form.title.trim() || 'Nháp không tiêu đề';
        const { data } = await axios.post(
            route('knowledge-base.articles.store'),
            {
                category_id: form.category_id,
                title,
                slug: form.slug || slugifyTitle(title),
                excerpt: form.excerpt,
                content: form.content,
                status: 'draft',
                tag_names: form.tag_names?.length ? form.tag_names : [],
            },
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
                },
            },
        );
        if (data?.slug) {
            bootstrappedSlug.value = data.slug;
            return route('knowledge-base.articles.images.store', data.slug);
        }
        toast.error('Không tạo được bản nháp để lưu ảnh.');
        return '';
    } catch {
        toast.error('Không tạo được bản nháp để lưu ảnh. Kiểm tra tiêu đề và danh mục.');
        return '';
    } finally {
        bootstrapInFlight.value = false;
    }
}

const form = useForm({
    category_id: props.article?.category_id ?? (props.categories.data?.[0]?.id ?? ''),
    title: props.article?.title ?? '',
    slug: props.article?.slug ?? '',
    excerpt: props.article?.excerpt ?? '',
    content: props.article?.content ?? '',
    status: props.article?.status?.value ?? 'draft',
    tag_names: (props.article?.tags ?? []).map((t) => t.name),
});

watch(
    () => form.title,
    (title) => {
        form.slug = slugifyTitle(title);
    },
    { immediate: true },
);

const categoryName = computed(() => {
    const id = form.category_id;
    const cat = (props.categories.data ?? []).find((c) => String(c.id) === String(id));
    return cat?.name ?? '—';
});

const statusLabel = computed(() => {
    const opt = (props.options.statuses ?? []).find((s) => s.value === form.status);
    return opt?.label ?? form.status;
});

const previewPath = computed(() => (
    form.slug ? `/knowledge-base/articles/${form.slug}` : '/knowledge-base/articles/…'
));

function submit() {
    const payload = {
        ...form.data(),
        tag_names: form.tag_names?.length ? form.tag_names : [],
    };

    if (persistedSlug.value) {
        form.transform(() => payload).put(`/knowledge-base/articles/${persistedSlug.value}`, { preserveScroll: true });
    } else {
        form.transform(() => payload).post('/knowledge-base/articles');
    }
}

function togglePreview() {
    previewMode.value = !previewMode.value;
}
</script>

<template>
  <Head :title="isEdit ? 'Sửa bài viết' : 'Viết bài mới'" />
  <AppLayout>
    <template #header>
      <PageHeader
        :title="isEdit ? 'Sửa bài viết' : 'Viết bài mới'"
        :subtitle="previewMode ? 'Xem trước như trên trang công khai nội bộ' : 'Soạn thảo tài liệu cho cơ sở tri thức'"
        icon="knowledge"
        icon-color="brand"
        back-href="/knowledge-base"
      >
        <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
          <button
            type="button"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs font-semibold"
            @click="togglePreview"
          >
            <AppIcon
              :name="previewMode ? 'edit' : 'eye'"
              :size="15"
            />
            {{ previewMode ? 'Chỉnh sửa' : 'Xem trước' }}
          </button>
          <Link
            href="/knowledge-base"
            class="btn-ghost inline-flex h-9 items-center gap-1.5 px-3 text-xs font-semibold"
          >
            Huỷ
          </Link>
          <button
            v-if="!previewMode"
            type="button"
            class="btn-primary inline-flex h-9 items-center gap-1.5 px-3 text-xs font-semibold"
            :disabled="form.processing"
            @click="submit"
          >
            <AppIcon
              name="save"
              :size="15"
            />
            {{ isEdit ? 'Lưu bài' : 'Tạo bài' }}
          </button>
        </div>
      </PageHeader>
    </template>

    <!-- Preview — bố cục gần KnowledgeBase/Show -->
    <div
      v-if="previewMode"
      class="w-full space-y-4"
    >
      <p class="rounded-lg border border-sky-200/80 bg-sky-50 px-4 py-2.5 text-xs text-sky-800">
        Đây là bản xem trước — chưa lưu. URL dự kiến:
        <span class="font-mono text-[11px]">{{ previewPath }}</span>
      </p>
      <article class="card p-5 lg:p-8">
        <div class="mb-4 flex flex-wrap items-center gap-2 text-sm text-slate-500">
          <Badge
            :label="statusLabel"
            color="slate"
          />
          <span>{{ categoryName }}</span>
        </div>
        <h2 class="font-display text-2xl font-semibold text-slate-800">
          {{ form.title.trim() || 'Tiêu đề bài viết' }}
        </h2>
        <div
          v-if="form.tag_names?.length"
          class="mt-3 flex flex-wrap gap-1.5"
        >
          <span
            v-for="tag in form.tag_names"
            :key="tag"
            class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-medium text-slate-600"
          >
            #{{ tag }}
          </span>
        </div>
        <div
          v-if="form.excerpt?.trim()"
          class="mt-4 border-l-2 border-brand/30 pl-3 text-sm italic text-slate-500"
        >
          <RichContentBody
            :content="form.excerpt"
            empty-text=""
            html-class="prose prose-sm max-w-none text-slate-500 italic"
            plain-class="text-sm italic text-slate-500"
          />
        </div>
        <div class="mt-6 border-t border-slate-100 pt-6">
          <RichContentBody
            :content="form.content"
            empty-text="Chưa có nội dung — quay lại chỉnh sửa để thêm."
            html-class="rich-content prose prose-sm max-w-none text-slate-700"
            empty-class="text-sm text-slate-400 italic"
          />
        </div>
      </article>
    </div>

    <form
      v-else
      class="w-full space-y-5"
      @submit.prevent="submit"
    >
      <div class="grid grid-cols-1 gap-5 xl:grid-cols-12">
        <!-- Cột nội dung chính -->
        <div class="card space-y-5 p-5 xl:col-span-8">
          <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
              <label class="label mb-1 flex items-center gap-1.5">
                Tiêu đề
                <span
                  class="text-danger"
                  aria-hidden="true"
                >*</span>
                <FieldTooltip text="Tiêu đề hiển thị trên trang bài viết và kết quả tìm kiếm." />
              </label>
              <input
                v-model="form.title"
                class="input h-10 w-full text-sm"
                placeholder="VD: Hướng dẫn triển khai tính năng X trên môi trường staging"
                required
                maxlength="500"
              >
              <p
                v-if="form.errors.title"
                class="mt-1 text-xs text-danger"
              >
                {{ form.errors.title }}
              </p>
            </div>

            <div class="sm:col-span-2">
              <label class="label mb-1 flex items-center gap-1.5">
                Slug SEO
                <FieldTooltip
                  wide
                  text="Đường dẫn thân thiện URL, tự sinh từ tiêu đề. Hệ thống đảm bảo duy nhất khi lưu."
                />
              </label>
              <div class="flex min-w-0 rounded-input border border-slate-200 bg-slate-50 focus-within:border-brand focus-within:ring-1 focus-within:ring-brand/30">
                <span class="hidden shrink-0 items-center border-r border-slate-200 px-3 text-xs text-slate-400 sm:inline-flex">
                  /articles/
                </span>
                <input
                  v-model="form.slug"
                  class="input h-10 w-full min-w-0 border-0 bg-transparent text-sm text-slate-600 shadow-none focus:ring-0"
                  disabled
                  aria-readonly="true"
                  :placeholder="form.title ? slugifyTitle(form.title) : 'tu-dong-tu-tieu-de'"
                >
              </div>
              <p class="mt-1 text-[11px] text-slate-400">
                Cập nhật theo tiêu đề ·
                <span class="font-mono">{{ previewPath }}</span>
              </p>
              <p
                v-if="form.errors.slug"
                class="mt-1 text-xs text-danger"
              >
                {{ form.errors.slug }}
              </p>
            </div>
          </div>

          <KbRichTextField
            v-model="form.excerpt"
            label="Mô tả ngắn"
            placeholder="Tóm tắt 1–2 câu giúp người đọc nắm nội dung trước khi mở bài…"
            tooltip="Hiển thị dưới tiêu đề trên thẻ bài viết và trang chi tiết."
            :error="form.errors.excerpt"
            :image-upload-url="imageUploadUrl"
            :resolve-image-upload-url="resolveImageUploadUrl"
            hint="Có thể chèn ảnh bằng nút 🖼, kéo thả hoặc Ctrl+V."
            editor-min-height-class="min-h-[100px]"
          />

          <KbRichTextField
            v-model="form.content"
            label="Nội dung"
            placeholder="Viết nội dung chi tiết: bước thực hiện, lưu ý, hình minh hoạ…"
            tooltip="Nội dung chính của bài — hỗ trợ định dạng, liên kết và ảnh."
            required
            :error="form.errors.content"
            :image-upload-url="imageUploadUrl"
            :resolve-image-upload-url="resolveImageUploadUrl"
            hint="Chèn ảnh: nút 🖼, kéo thả vào khung, hoặc dán ảnh từ clipboard (Ctrl+V)."
            editor-min-height-class="min-h-[280px]"
          />

          <KbImageGallery
            v-if="persistedSlug"
            :article-slug="persistedSlug"
            :images="article?.gallery_images ?? []"
          />
        </div>

        <!-- Cột meta -->
        <div class="space-y-5 xl:col-span-4">
          <div class="card space-y-4 p-5">
            <h3 class="font-display text-sm font-semibold text-slate-800">
              Thông tin xuất bản
            </h3>

            <div>
              <label class="label mb-1 flex items-center gap-1.5">
                Danh mục
                <span
                  class="text-danger"
                  aria-hidden="true"
                >*</span>
                <FieldTooltip text="Nhóm bài trong cơ sở tri thức — ảnh hưởng lọc và bài liên quan." />
              </label>
              <select
                v-model="form.category_id"
                class="input h-10 w-full text-sm"
                required
              >
                <option
                  disabled
                  value=""
                >
                  Chọn danh mục…
                </option>
                <option
                  v-for="c in categories.data"
                  :key="c.id"
                  :value="c.id"
                >
                  {{ c.name }}
                </option>
              </select>
              <p
                v-if="form.errors.category_id"
                class="mt-1 text-xs text-danger"
              >
                {{ form.errors.category_id }}
              </p>
            </div>

            <div>
              <label class="label mb-1 flex items-center gap-1.5">
                Trạng thái
                <span
                  class="text-danger"
                  aria-hidden="true"
                >*</span>
                <FieldTooltip
                  wide
                  text="Nháp: chỉ người soạn thấy. Xuất bản: hiển thị với mọi thành viên có quyền xem."
                />
              </label>
              <select
                v-model="form.status"
                class="input h-10 w-full text-sm"
              >
                <option
                  v-for="s in options.statuses"
                  :key="s.value"
                  :value="s.value"
                >
                  {{ s.label }}
                </option>
              </select>
              <p
                v-if="form.errors.status"
                class="mt-1 text-xs text-danger"
              >
                {{ form.errors.status }}
              </p>
            </div>
          </div>

          <div class="card p-5">
            <KbTagField
              v-model="form.tag_names"
              :suggestions="tagSuggestions"
              :error="form.errors.tag_names"
            />
          </div>

          <div class="card border-dashed border-slate-200 bg-slate-50/50 p-4 text-xs leading-relaxed text-slate-500">
            <p class="mb-2 font-semibold text-slate-600">
              Mẹo soạn thảo
            </p>
            <ul class="list-disc space-y-1 pl-4">
              <li>Dùng tiêu đề rõ ràng — slug SEO cập nhật ngay.</li>
              <li>Chèn ảnh vào mô tả hoặc nội dung bằng nút 🖼, kéo thả hoặc dán (Ctrl+V).</li>
              <li>Bấm <strong class="font-medium text-slate-700">Xem trước</strong> trên header để kiểm tra bố cục.</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
        <button
          type="button"
          class="btn-ghost h-10 px-4 text-sm"
          @click="togglePreview"
        >
          Xem trước trang
        </button>
        <button
          type="submit"
          class="btn-primary inline-flex h-10 items-center gap-2 px-4 text-sm"
          :disabled="form.processing"
        >
          <AppIcon
            name="save"
            :size="16"
          />
          {{ isEdit ? 'Lưu thay đổi' : 'Tạo bài viết' }}
        </button>
      </div>
    </form>
  </AppLayout>
</template>
