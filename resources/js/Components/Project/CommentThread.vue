<script setup>
import { useForm } from '@inertiajs/vue3';
import Avatar from '@/Components/Project/Avatar.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { datetime } from '@/composables/useFormat';

const props = defineProps({
    comments: { type: Array, default: () => [] },
    commentableType: { type: String, required: true }, // bug | feedback | blocker | task
    commentableId: { type: [Number, String], required: true },
    canComment: { type: Boolean, default: true },
});

const form = useForm({
    commentable_type: props.commentableType,
    commentable_id: props.commentableId,
    body: '',
});

const submit = () => {
    if (!form.body.trim()) return;
    form.post('/comments', {
        preserveScroll: true,
        onSuccess: () => form.reset('body'),
    });
};
</script>

<template>
    <div>
        <h3 class="mb-3 flex items-center gap-2 font-display font-semibold text-slate-800">
            <AppIcon name="comment" :size="18" /> Trao đổi
            <span class="text-sm font-normal text-slate-400">({{ comments.length }})</span>
        </h3>

        <form v-if="canComment" class="mb-5 flex gap-2" @submit.prevent="submit">
            <textarea
                v-model="form.body"
                rows="2"
                class="input flex-1 resize-none"
                placeholder="Viết phản hồi cho người xử lý…"
            ></textarea>
            <button type="submit" class="btn-primary self-end" :disabled="form.processing || !form.body.trim()">
                Gửi
            </button>
        </form>

        <ul class="space-y-4">
            <li v-for="c in comments" :key="c.id" class="flex gap-3">
                <Avatar :name="c.author?.name" :src="c.author?.avatar_path" :size="34" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-baseline gap-2">
                        <span class="text-sm font-medium text-slate-800">{{ c.author?.name }}</span>
                        <span class="text-xs text-slate-400">{{ datetime(c.created_at) }}</span>
                    </div>
                    <p class="mt-0.5 whitespace-pre-wrap text-sm text-slate-600">{{ c.body }}</p>
                </div>
            </li>
            <li v-if="comments.length === 0" class="text-sm text-slate-400">Chưa có trao đổi nào.</li>
        </ul>
    </div>
</template>
