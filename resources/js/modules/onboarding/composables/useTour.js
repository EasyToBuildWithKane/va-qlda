import { driver } from 'driver.js';
import 'driver.js/dist/driver.css';
import { getTour } from '@/modules/onboarding/tours';

/**
 * Thin wrapper around driver.js — keeps the library out of .vue files and maps
 * our tour definitions to driver's step/popover shape. Steps whose anchor is not
 * in the DOM (e.g. nav items hidden for the user's role) are dropped so a tour
 * never breaks on a missing selector.
 */
export function useTour() {
    function start(tourKey, { onStep, onComplete, onSkip } = {}) {
        const def = getTour(tourKey);
        if (!def) return null;

        const steps = def.steps
            .filter((s) => !s.el || document.querySelector(s.el))
            .map((s) => ({
                element: s.el,
                popover: {
                    title: s.title,
                    description: s.purpose
                        ? `<p>${s.desc}</p><p class="va-tour-purpose"><span>🎯 Mục đích</span> ${s.purpose}</p>`
                        : `<p>${s.desc}</p>`,
                    side: s.side || 'right',
                    align: s.align || 'start',
                },
            }));

        if (steps.length === 0) return null;

        let driverObj;
        driverObj = driver({
            showProgress: true,
            progressText: 'Bước {{current}}/{{total}}',
            nextBtnText: 'Tiếp theo →',
            prevBtnText: '← Quay lại',
            doneBtnText: 'Hoàn thành 🎉',
            stagePadding: 6,
            stageRadius: 10,
            overlayColor: '#0f172a',
            overlayOpacity: 0.6,
            popoverClass: 'va-tour-popover',
            allowClose: true,
            steps,
            onHighlighted: (_el, _step, opts) => {
                onStep?.(opts.state.activeIndex + 1, steps.length);
            },
            onDestroyStarted: () => {
                // Reached the end → "Hoàn thành"; closed early (X / overlay) → bỏ qua.
                const finished = !driverObj.hasNextStep();
                driverObj.destroy();
                if (finished) onComplete?.(steps.length);
                else onSkip?.();
            },
        });

        driverObj.drive();
        return driverObj;
    }

    return { start };
}
