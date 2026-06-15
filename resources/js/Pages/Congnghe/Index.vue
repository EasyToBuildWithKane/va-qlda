<script setup>
import { Head } from '@inertiajs/vue3';
import AuroraBackground from './partials/AuroraBackground.vue';
import ScrollProgress from './partials/ScrollProgress.vue';
import CursorGlow from './partials/CursorGlow.vue';
import CongngheNavbar from './partials/CongngheNavbar.vue';
import HeroSection from './partials/HeroSection.vue';
import AboutSection from './partials/AboutSection.vue';
import ImpactMetrics from './partials/ImpactMetrics.vue';
import ProductEcosystem from './partials/ProductEcosystem.vue';
import TechStack from './partials/TechStack.vue';
import OrgChartSection from './partials/OrgChartSection.vue';
import ProjectTimeline from './partials/ProjectTimeline.vue';
import AILabSection from './partials/AILabSection.vue';
import CultureSection from './partials/CultureSection.vue';
import RoadmapSection from './partials/RoadmapSection.vue';
import CongngheFooter from './partials/CongngheFooter.vue';
import CongngheProjectDetailModal from './partials/CongngheProjectDetailModal.vue';

defineProps({
    metrics: { type: Object, default: () => ({}) },
    phases: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    org: { type: Object, default: () => ({ overview: {}, forest: [], people: {} }) },
});
</script>

<template>
  <Head title="Phòng Công Nghệ" />

  <div class="relative min-h-screen overflow-x-hidden bg-[#05060c] font-sans text-white antialiased selection:bg-brand/40 selection:text-white">
    <AuroraBackground />
    <ScrollProgress />
    <CursorGlow />
    <CongngheNavbar />

    <main class="relative z-10">
      <HeroSection :metrics="metrics" />
      <AboutSection />
      <ImpactMetrics :metrics="metrics" />
      <ProductEcosystem :products="products" />
      <TechStack />
      <OrgChartSection
        :overview="org.overview"
        :forest="org.forest"
        :people="org.people"
      />
      <ProjectTimeline :phases="phases" />
      <AILabSection />
      <CultureSection />
      <RoadmapSection />
    </main>

    <CongngheFooter />
    <CongngheProjectDetailModal />
  </div>
</template>

<style>
/* PNG thương hiệu xuất kèm nền đen — chỉ hiện họa tiết trên nền landing tối */
.cn-brand-cutout {
    mix-blend-mode: screen;
}
</style>
