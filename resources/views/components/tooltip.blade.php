@props([
    'text' => null,
    'width' => 'w-56',
])

<span {{ $attributes->merge(['class' => 'group relative inline-flex items-center']) }}
      x-data="{ tip: false, tx: 0, ty: 0 }"
      @mouseenter="tip = true; tx = $event.clientX; ty = $event.clientY"
      @mousemove="tx = $event.clientX; ty = $event.clientY"
      @mouseleave="tip = false">
    <span tabindex="0"
          @focus="tip = true; tx = $el.getBoundingClientRect().left; ty = $el.getBoundingClientRect().bottom"
          @blur="tip = false"
          class="inline-flex items-center focus:outline-none">
        {{ $slot }}
    </span>
    <template x-teleport="body">
        <div role="tooltip"
             x-show="tip"
             x-cloak
             x-transition.opacity.duration.150ms
             class="fixed z-[300] {{ $width }} max-w-[calc(100vw-2rem)] rounded-lg bg-gray-900 dark:bg-gray-700 px-3 py-2 text-xs font-normal normal-case text-left leading-relaxed text-white shadow-2xl ring-1 ring-black/10 pointer-events-none"
             :style="`top: ${ty + 18}px; left: ${Math.min(tx + 12, window.innerWidth - 280)}px;`">
            @if (isset($content))
                {{ $content }}
            @else
                {{ $text }}
            @endif
        </div>
    </template>
</span>
