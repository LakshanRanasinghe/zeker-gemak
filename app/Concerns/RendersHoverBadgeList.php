<?php

namespace App\Concerns;

trait RendersHoverBadgeList
{
    protected function renderHoverBadgeList(array $items, string $emptyState = '—'): string
    {
        $items = array_values(array_filter(
            array_map(static fn ($item) => trim((string) $item), $items),
            static fn ($item) => $item !== ''
        ));

        if ($items === []) {
            return $emptyState;
        }

        $badgeClass = 'inline-flex items-center rounded-md bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';

        if (count($items) === 1) {
            return '<span class="'.$badgeClass.'">'.e($items[0]).'</span>';
        }

        $badges = implode('', array_map(
            fn ($item) => '<span class="'.$badgeClass.'">'.e($item).'</span>',
            $items
        ));

        $template = <<<'HTML'
<div
    x-data="{
        open: false,
        top: 0,
        left: 0,
        closeTimer: null,
        updatePosition() {
            const trigger = this.$refs.trigger;
            if (! trigger) return;

            const rect = trigger.getBoundingClientRect();
            const panelWidth = this.$refs.panel?.offsetWidth ?? 0;
            const panelHeight = this.$refs.panel?.offsetHeight ?? 0;
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const maxLeft = Math.max(12, viewportWidth - panelWidth - 12);
            const preferredLeft = Math.max(12, Math.min(rect.left, maxLeft));
            const belowTop = rect.bottom + 8;
            const aboveTop = rect.top - panelHeight - 8;

            this.left = preferredLeft;
            this.top = belowTop + panelHeight <= viewportHeight - 12 || aboveTop < 12
                ? belowTop
                : aboveTop;
        },
        show() {
            clearTimeout(this.closeTimer);
            this.open = true;
            this.$nextTick(() => this.updatePosition());
        },
        hide() {
            clearTimeout(this.closeTimer);
            this.closeTimer = setTimeout(() => { this.open = false; }, 120);
        },
        keepOpen() {
            clearTimeout(this.closeTimer);
        }
    }"
    x-on:keydown.escape.window="open = false"
    x-on:resize.window="open && updatePosition()"
    x-on:scroll.window="open && updatePosition()"
    class="inline-block"
>
    <div
        x-ref="trigger"
        x-on:mouseenter="show()"
        x-on:mouseleave="hide()"
        class="inline-flex cursor-pointer items-center gap-1"
    >
        <span class="__BADGE_CLASS__">__FIRST_ITEM__</span>
        <span class="text-xs text-zinc-400">+__EXTRA_COUNT__</span>
    </div>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-transition.opacity.duration.100ms
            x-ref="panel"
            x-on:mouseenter="keepOpen()"
            x-on:mouseleave="hide()"
            class="fixed z-[1000]"
            x-bind:style="`top: ${top}px; left: ${left}px;`"
        >
            <div class="flex max-w-sm flex-wrap gap-1 rounded-lg border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                __BADGES__
            </div>
        </div>
    </template>
</div>
HTML;

        return strtr($template, [
            '__BADGE_CLASS__' => $badgeClass,
            '__FIRST_ITEM__' => e($items[0]),
            '__EXTRA_COUNT__' => (string) (count($items) - 1),
            '__BADGES__' => $badges,
        ]);
    }
}
