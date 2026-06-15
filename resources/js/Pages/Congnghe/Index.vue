<script setup>
import { Head } from '@inertiajs/vue3';
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

defineProps({
    metrics: { type: Object, default: () => ({}) },
    phases: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    org: { type: Object, default: () => ({ overview: {}, forest: [] }) },
});
</script>

<template>
  <Head title="Phòng Công Nghệ" />

  <div class="min-h-screen overflow-x-hidden bg-[#0b0b12] font-sans text-white antialiased">
    <CongngheNavbar />

    <main>
      <HeroSection :metrics="metrics" />
      <AboutSection />
      <ImpactMetrics :metrics="metrics" />
      <ProductEcosystem :products="products" />
      <TechStack />
      <OrgChartSection
        :overview="org.overview"
        :forest="org.forest"
      />
      <ProjectTimeline :phases="phases" />
      <AILabSection :metrics="metrics" />
      <CultureSection />
      <RoadmapSection />
    </main>

    <CongngheFooter />
  </div>
</template>
