<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import KbRichTextField from '@/Components/KnowledgeBase/KbRichTextField.vue';
import KbImageGallery from '@/Components/KnowledgeBase/KbImageGallery.vue';

const props = defineProps({
    article: { type: Object, default: null },
    categories: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
});

const isEdit = computed(() => Boolean(props.article?.id));

const imageUploadUrl = computed(() => (
    isEdit.value && props.article?.slug
        ? route('knowledge-base.articles.images.store', props.article.slug)
        : ''
));

const form = useForm({
    category_id: props.article?.category_id ?? (props.categories.data?.[0]?.id ?? ''),
    title: props.article?.title ?? '',
    slug: props.article?.slug ?? '',
    excerpt: props.article?.excerpt ?? '',
    content: props.article?.content ?? '',
    status: props.article?.status?.value ?? 'draft',
    tag_names: (props.article?.tags ?? []).map((t) => t.name).join(', '),
});

function submit() {
    const payload = {
        ...form.data(),
        tag_names: form.tag_names
            ? form.tag_names.split(',').map((s) => s.trim()).filter(Boolean)
            : [],
    };

    if (isEdit.value) {
        form.transform(() => payload).put(`/knowledge-base/articles/${props.article.slug}`, { preserveScroll: true });
    } else {
        form.transform(() => payload).post('/knowledge-base/articles');
    }
}
</script>

<template>
  <Head :title="isEdit ? 'Sửa bài viết' : 'Viết bài mới'" />
  <AppLayout>
    <PageHeader
      :title="isEdit ? 'Sửa bài viết' : 'Viết bài mới'"
    />

    <form
      class="card mx-auto max-w-3xl space-y-4 p-5"
      @submit.prevent="submit"
    >
      <div>
        <label class="label">Danh mục</label>
        <select
          v-model="form.category_id"
          class="input w-full"
          required
        >
          <option
            v-for="c in categories.data"
            :key="c.id"
            :value="c.id"
          >
            {{ c.name }}
          </option>
        </select>
      </div>
      <div>
        <label class="label">Tiêu đề</label>
        <input
          v-model="form.title"
          class="input w-full"
          required
        >
      </div>
      <div>
        <label class="label">Slug SEO</label>
        <input
          v-model="form.slug"
          class="input w-full"
          placeholder="Tự động nếu để trống"
        >
      </div>
      <div>
        <label class="label">Mô tả ngắn</label>
        <textarea
          v-model="form.excerpt"
          class="input w-full"
          rows="2"
        />
      </div>
      <KbRichTextField
        v-model="form.content"
        label="Nội dung"
        placeholder="Viết nội dung bài..."
        :image-upload-url="imageUploadUrl"
        hint="Lưu bài trước khi chèn ảnh (tự động sau khi tạo bài mới)."
      />
      <KbImageGallery
        v-if="isEdit"
        :article-slug="article.slug"
        :images="article.gallery_images ?? []"
      />
      <div>
        <label class="label">Thẻ (phân cách bằng dấu phẩy)</label>
        <input
          v-model="form.tag_names"
          class="input w-full"
        >
      </div>
      <div>
        <label class="label">Trạng thái</label>
        <select
          v-model="form.status"
          class="input w-full"
        >
          <option
            v-for="s in options.statuses"
            :key="s.value"
            :value="s.value"
          >
            {{ s.label }}
          </option>
        </select>
      </div>
      <div class="flex justify-end gap-2">
        <button
          type="submit"
          class="btn-primary"
          :disabled="form.processing"
        >
          {{ isEdit ? 'Lưu' : 'Tạo bài' }}
        </button>
      </div>
    </form>
  </AppLayout>
</template>
