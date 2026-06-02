<script setup>
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    username: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Sign in" />

    <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-brand-900 via-brand-700 to-brand p-4">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <div class="inline-flex h-12 w-12 items-center justify-center rounded-card bg-accent/20 text-accent font-display font-extrabold text-xl mb-3">
                    VA
                </div>
                <h1 class="font-display text-2xl font-bold text-white">Team & Project Governance</h1>
                <p class="text-brand-100 text-sm mt-1">Phòng Công Nghệ — Internal System</p>
            </div>

            <form class="card p-8 space-y-5" @submit.prevent="submit">
                <div>
                    <label for="username" class="label">Username</label>
                    <input
                        id="username"
                        v-model="form.username"
                        type="text"
                        class="input"
                        autocomplete="username"
                        autofocus
                    />
                    <p v-if="form.errors.username" class="mt-1 text-sm text-danger">
                        {{ form.errors.username }}
                    </p>
                </div>

                <div>
                    <label for="password" class="label">Password</label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="input"
                        autocomplete="current-password"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-danger">
                        {{ form.errors.password }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300" />
                    Remember me
                </label>

                <button type="submit" class="btn-primary w-full" :disabled="form.processing">
                    {{ form.processing ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>

            <p class="text-center text-xs text-brand-100/70 mt-6">
                v2.0 · MVP · Laravel 10 + Inertia/Vue 3
            </p>
        </div>
    </div>
</template>
