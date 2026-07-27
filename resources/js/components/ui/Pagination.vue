<template>
  <div v-if="meta && meta.last_page > 1" class="flex items-center justify-between pt-4">
    <p class="text-sm text-gray-500 dark:text-gray-400">
      {{ meta.from }}–{{ meta.to }} / {{ meta.total }}
    </p>
    <div class="flex items-center gap-1">
      <button
        v-for="page in pages"
        :key="page"
        :disabled="page === '…'"
        @click="page !== '…' && $emit('change', page)"
        class="w-8 h-8 rounded-lg text-sm flex items-center justify-center transition"
        :class="page === meta.current_page
          ? 'bg-brand-600 text-white font-semibold'
          : page === '…'
            ? 'text-gray-400 cursor-default'
            : 'hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-700 dark:text-gray-300'"
      >
        {{ page }}
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ meta: Object })
defineEmits(['change'])

const pages = computed(() => {
  if (!props.meta) return []
  const { current_page: cur, last_page: last } = props.meta
  const all = []
  for (let i = 1; i <= last; i++) {
    if (i === 1 || i === last || (i >= cur - 1 && i <= cur + 1)) all.push(i)
    else if (all[all.length - 1] !== '…') all.push('…')
  }
  return all
})
</script>
