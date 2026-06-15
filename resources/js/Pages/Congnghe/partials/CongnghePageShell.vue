<script setup>
import AuroraBackground from './AuroraBackground.vue';
import CongngheNavbar from './CongngheNavbar.vue';
import CongngheFooter from './CongngheFooter.vue';
import CongngheMascotAssistant from './CongngheMascotAssistant.vue';
</script>

<template>
  <div
    class="relative min-h-screen overflow-x-hidden bg-[#05060c] font-sans text-white antialiased selection:bg-brand/40 selection:text-white"
  >
    <AuroraBackground />
    <CongngheNavbar />

    <main class="relative z-10 px-4 pb-16 pt-28 sm:px-6 sm:pt-32 lg:pb-24">
      <slot />
    </main>

    <CongngheFooter />
    <CongngheMascotAssistant />
  </div>
</template>
