<script setup>
import { computed, provide } from 'vue';
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
import CongngheMascotAssistant from './partials/CongngheMascotAssistant.vue';
import CongngheProjectDetailModal from './partials/CongngheProjectDetailModal.vue';

const props = defineProps({
    content: {
        type: Object,
        default: () => ({ meta: {}, nav: {}, hero: {}, footer: {}, sections: [] }),
    },
    icons: { type: Object, default: () => ({}) },
    metrics: { type: Object, default: () => ({}) },
    phases: { type: Array, default: () => [] },
    products: { type: Array, default: () => [] },
    org: {
        type: Object,
        default: () => ({ overview: {}, forest: [], people: {} }),
    },
});

// Bản đồ biểu tượng (key → SVG path) cho các partial dùng icon item.
provide('congngheIcons', props.icons);

const sectionComponents = {
    about: AboutSection,
    impact: ImpactMetrics,
    products: ProductEcosystem,
    tech: TechStack,
    org: OrgChartSection,
    lifecycle: ProjectTimeline,
    ai_lab: AILabSection,
    culture: CultureSection,
    roadmap: RoadmapSection,
};

const pageTitle = computed(() => props.content?.meta?.title || 'Phòng Công Nghệ');
const sections = computed(
    () => (props.content?.sections ?? []).filter((s) => sectionComponents[s.key]),
);

// Dữ liệu thật bổ sung cho từng section (số liệu vẫn tính từ DB).
function sectionDataProps(key) {
    switch (key) {
    case 'impact':
        return { metrics: props.metrics };
    case 'products':
        return { products: props.products };
    case 'lifecycle':
        return { phases: props.phases };
    case 'org':
        return {
            overview: props.org.overview,
            forest: props.org.forest,
            people: props.org.people,
        };
    default:
        return {};
    }
}
</script>

<template>
  <Head :title="pageTitle" />

  <div
    class="cn-anim-root relative min-h-screen overflow-x-hidden bg-[#05060c] font-sans text-white antialiased selection:bg-brand/40 selection:text-white"
  >
    <AuroraBackground />
    <ScrollProgress />
    <CursorGlow />
    <CongngheNavbar :content="content.nav" />

    <main class="relative z-10 pb-4 sm:pb-8 lg:pb-0">
      <HeroSection
        :content="content.hero"
        :metrics="metrics"
      />

      <component
        :is="sectionComponents[section.key]"
        v-for="section in sections"
        :key="section.key"
        :content="section.data"
        v-bind="sectionDataProps(section.key)"
      />
    </main>

    <CongngheFooter :content="content.footer" />
    <CongngheMascotAssistant />
    <CongngheProjectDetailModal />
  </div>
</template>
