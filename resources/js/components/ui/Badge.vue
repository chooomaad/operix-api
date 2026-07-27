<template>
  <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium" :class="cls">
    <slot>{{ label }}</slot>
  </span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ value: String, label: String })

const map = {
  // status
  open:           'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
  in_progress:    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  closed:         'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
  resolved:       'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  active:         'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  inactive:       'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
  suspended:      'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  expired:        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  in:             'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400',
  out:            'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
  operational:    'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
  maintenance:    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  out_of_service: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  retired:        'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
  // severity
  low:      'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
  medium:   'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  high:     'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
  critical: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
  // breach severity
  avertissement: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
  blame:         'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
  mise_a_pied:   'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
  licenciement:  'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
}

const cls = computed(() => map[props.value] || 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400')
</script>
