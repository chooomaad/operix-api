<template>
  <div class="flex flex-col gap-1">
    <label v-if="label" class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ label }}</label>
    <slot />
    <p v-if="error" class="text-xs text-red-500">{{ Array.isArray(error) ? error[0] : error }}</p>
  </div>
</template>
<script setup>
defineProps({ label: String, error: [String, Array] })
</script>
