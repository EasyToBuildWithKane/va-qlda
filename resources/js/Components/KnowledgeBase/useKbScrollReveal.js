import { onMounted, onUnmounted } from 'vue';

export function useKbScrollReveal(rootRef) {
    let observer = null;

    onMounted(() => {
        const root = rootRef?.value;
        if (!root || typeof IntersectionObserver === 'undefined') return;

        const nodes = root.querySelectorAll(':scope > *');
        nodes.forEach((el, i) => {
            el.classList.add('kb-reveal');
            el.style.setProperty('--kb-reveal-delay', `${Math.min(i * 40, 200)}ms`);
        });

        observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('kb-reveal--visible');
                        observer?.unobserve(entry.target);
                    }
                });
            },
            { rootMargin: '0px 0px -8% 0px', threshold: 0.05 },
        );

        nodes.forEach((el) => observer.observe(el));
    });

    onUnmounted(() => {
        observer?.disconnect();
    });
}
