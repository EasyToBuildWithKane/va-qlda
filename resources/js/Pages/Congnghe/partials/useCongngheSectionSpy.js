import { onBeforeUnmount, onMounted, ref } from 'vue';

const DEFAULT_IDS = [
    'top',
    'gioi-thieu',
    'thanh-tuu',
    'san-pham',
    'cong-nghe',
    'to-chuc',
    'du-an',
    'ai-lab',
    'van-hoa',
    'lo-trinh',
];

/**
 * Theo dõi section đang hiển thị trên viewport (landing /congnghe).
 */
export function useCongngheSectionSpy(ids = DEFAULT_IDS) {
    const activeId = ref('top');
    const visibleSections = new Map();
    let spy = null;

    onMounted(() => {
        if (typeof IntersectionObserver === 'undefined') {
            return;
        }

        spy = new IntersectionObserver(
            (entries) => {
                entries.forEach((e) => {
                    if (e.isIntersecting) {
                        visibleSections.set(e.target.id, e.intersectionRatio);
                    } else {
                        visibleSections.delete(e.target.id);
                    }
                });

                let bestId = activeId.value;
                let bestRatio = 0;
                visibleSections.forEach((ratio, id) => {
                    if (ratio >= bestRatio) {
                        bestRatio = ratio;
                        bestId = id;
                    }
                });
                if (bestRatio > 0) {
                    activeId.value = bestId;
                }
            },
            { rootMargin: '-38% 0px -45% 0px', threshold: [0, 0.12, 0.3, 0.5, 0.75] },
        );

        ids.forEach((id) => {
            const el = document.getElementById(id);
            if (el) {
                spy.observe(el);
            }
        });
    });

    onBeforeUnmount(() => spy?.disconnect());

    return { activeId };
}
