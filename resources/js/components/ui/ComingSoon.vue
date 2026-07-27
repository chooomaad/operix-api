<template>
  <div class="flex flex-col items-center justify-center py-24 text-gray-400">
    <Icon name="tool" class="w-10 h-10 mb-3" />
    <p class="text-lg font-medium">{{ title }}</p>
    <p class="text-sm mt-1">Module en cours de développement</p>
  </div>
</template>

<script setup>
import Icon from './Icon.vue'
defineProps({ title: { type: String, default: 'Bientôt disponible' } })
</script>
