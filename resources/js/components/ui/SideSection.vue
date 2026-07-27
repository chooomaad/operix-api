<template>
  <p class="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-widest select-none" style="color:#6899b8">
    {{ label }}
  </p>
</template>
<script setup>
defineProps({ label: String })
</script>
