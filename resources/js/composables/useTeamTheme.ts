import { usePage } from '@inertiajs/vue3';
import { watch, computed } from 'vue';
import type { AppPageProps } from '@/types';

/**
 * Convert a hex colour to HSL format for CSS custom properties.
 */
function hexToHsl(hex: string): { h: number; s: number; l: number } {
    const r = parseInt(hex.slice(1, 3), 16) / 255;
    const g = parseInt(hex.slice(3, 5), 16) / 255;
    const b = parseInt(hex.slice(5, 7), 16) / 255;

    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    let h = 0;
    let s = 0;
    const l = (max + min) / 2;

    if (max !== min) {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);

        switch (max) {
            case r:
                h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
                break;
            case g:
                h = ((b - r) / d + 2) / 6;
                break;
            case b:
                h = ((r - g) / d + 4) / 6;
                break;
        }
    }

    return {
        h: Math.round(h * 360),
        s: Math.round(s * 100),
        l: Math.round(l * 100),
    };
}

/**
 * Determine a readable foreground colour (white or dark) for a given background.
 */
function getForegroundHsl(hsl: { h: number; s: number; l: number }): string {
    return hsl.l > 55 ? 'hsl(0 0% 9%)' : 'hsl(0 0% 98%)';
}

/**
 * Composable that applies the team's primary colour as CSS custom property overrides.
 */
export function useTeamTheme() {
    const page = usePage<AppPageProps>();

    const primaryColor = computed(() => page.props.teamSettings?.primary_color);

    function applyTheme(color: string | null) {
        const root = document.documentElement;

        if (!color) {
            root.style.removeProperty('--primary');
            root.style.removeProperty('--primary-foreground');
            root.style.removeProperty('--ring');
            root.style.removeProperty('--sidebar-primary');
            root.style.removeProperty('--sidebar-primary-foreground');
            return;
        }

        const hsl = hexToHsl(color);
        const hslString = `hsl(${hsl.h} ${hsl.s}% ${hsl.l}%)`;
        const foreground = getForegroundHsl(hsl);

        root.style.setProperty('--primary', hslString);
        root.style.setProperty('--primary-foreground', foreground);
        root.style.setProperty('--ring', hslString);
        root.style.setProperty('--sidebar-primary', hslString);
        root.style.setProperty('--sidebar-primary-foreground', foreground);
    }

    watch(primaryColor, (color) => applyTheme(color), { immediate: true });

    return { primaryColor, applyTheme };
}
