<template>
  <div class="relative rounded-2xl border overflow-hidden transition-all duration-200 hover:scale-[1.01] cursor-default group"
       :class="dark
         ? 'bg-surface-800 border-surface-700 hover:border-surface-600 shadow-md shadow-black/20'
         : 'bg-white border-gray-200/80 hover:border-gray-300 shadow-sm hover:shadow-md'">
    <!-- Colored top accent bar -->
    <div class="h-1 w-full" :style="{ background: `linear-gradient(90deg, ${accent}, ${accent}88)` }"></div>

    <div class="p-5 flex items-start gap-4">
      <!-- Icon -->
      <div class="rounded-xl p-3 flex-shrink-0 transition-transform duration-200 group-hover:scale-110"
           :style="{ backgroundColor: accent + '1a', boxShadow: `0 0 0 1px ${accent}28` }">
        <Icon :name="icon" class="w-5 h-5" :style="{ color: accent }" />
      </div>

      <!-- Content -->
      <div class="min-w-0 flex-1 pt-0.5">
        <p class="text-2xl font-extrabold leading-none tracking-tight transition-colors"
           :class="dark ? 'text-white' : 'text-gray-900'">{{ value }}</p>
        <p class="text-sm font-medium mt-1 truncate transition-colors"
           :class="dark ? 'text-surface-300' : 'text-gray-600'">{{ label }}</p>
        <p v-if="sub" class="text-xs mt-1 truncate transition-colors"
           :class="dark ? 'text-surface-500' : 'text-gray-400'">{{ sub }}</p>
      </div>
    </div>

    <!-- Bottom glow -->
    <div class="absolute bottom-0 right-0 w-24 h-24 rounded-full opacity-5 blur-2xl pointer-events-none"
         :style="{ backgroundColor: accent }"></div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import Icon from './Icon.vue'
import { useThemeStore } from '../../stores/theme'

defineProps({
  accent: { type: String, default: '#2563eb' },
  icon:   { type: String, required: true },
  label:  { type: String, required: true },
  value:  { type: [Number, String], default: 0 },
  sub:    { type: String, default: '' },
})

const { dark } = useThemeStore()
</script>
