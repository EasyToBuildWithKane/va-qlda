<script setup>
import AuroraBackground from './AuroraBackground.vue';
import CongngheNavbar from './CongngheNavbar.vue';
import CongngheFooter from './CongngheFooter.vue';

const props = defineProps({
    chrome: {
        type: Object,
        default: () => ({ nav: {}, footer: {} }),
    },
});

const navContent = props.chrome?.nav ?? {};
const footerContent = props.chrome?.footer ?? {};
</script>

<template>
  <div
    class="relative min-h-screen overflow-x-hidden bg-[#05060c] font-sans text-white antialiased selection:bg-brand/40 selection:text-white"
  >
    <AuroraBackground />
    <CongngheNavbar :content="navContent" />

    <main class="relative z-10 px-4 pb-16 pt-28 sm:px-6 sm:pt-32 lg:pb-24">
      <slot />
    </main>

    <CongngheFooter :content="footerContent" />
  </div>
</template>
