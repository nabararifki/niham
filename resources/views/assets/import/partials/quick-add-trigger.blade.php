{{--
    Quick-add "+" for a Category / Department column header.

    @param string $entityType   'category' | 'department' — sent to quickAddEntity()
    @param string $modelClass   The policy subject, so the gate here is the exact one
                                CategoryController/DepartmentController authorize against.
    @param string $tooltipText  Already localized by the caller.

    Rendered behind @can, so a user without the create permission gets no element at
    all rather than a disabled one — nothing to enable from devtools, and no control
    advertising an action they cannot take.

    The tooltip is teleported to <body> instead of using the CSS-only group-hover
    pattern from mapping-page.blade.php. That pattern relies on nothing clipping it;
    here the table lives in an overflow-x-auto wrapper, which computes overflow-y to
    auto as well, so an absolutely-positioned tooltip inside a <th> would be cut off.
    Same escape hatch add-asset-modal.blade.php already uses for the same reason.

    No cursor-help: the hover affordance is the icon turning accent, matching every
    other icon button on this page.
--}}
@can('create', $modelClass)
    <span class="group relative inline-flex items-center"
          x-data="{ tip: false, tx: 0, ty: 0 }"
          @mouseenter="tip = true; tx = $event.clientX; ty = $event.clientY"
          @mousemove="tx = $event.clientX; ty = $event.clientY"
          @mouseleave="tip = false">
        <button type="button"
                @click="openQuickAdd('{{ $entityType }}')"
                @focus="tip = true; tx = $el.getBoundingClientRect().left; ty = $el.getBoundingClientRect().bottom"
                @blur="tip = false"
                data-quick-add="{{ $entityType }}"
                aria-label="{{ $tooltipText }}"
                class="text-gray-400 dark:text-gray-500 group-hover:text-accent focus:text-accent transition-colors focus:outline-none">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>
        <template x-teleport="body">
            <div role="tooltip"
                 x-show="tip"
                 x-cloak
                 x-transition.opacity.duration.150ms
                 class="fixed z-[300] w-56 max-w-[calc(100vw-2rem)] rounded-lg bg-gray-900 dark:bg-gray-700 px-3 py-2 text-xs font-normal normal-case text-left leading-relaxed text-white shadow-2xl ring-1 ring-black/10 pointer-events-none"
                 :style="`top: ${ty + 18}px; left: ${Math.min(tx + 12, window.innerWidth - 240)}px;`">
                {{ $tooltipText }}
            </div>
        </template>
    </span>
@endcan
