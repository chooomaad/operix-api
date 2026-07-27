<template>
  <router-link :to="to" custom v-slot="{ navigate }">
    <button @click="navigate"
      class="w-full flex items-center gap-2.5 px-2.5 py-2 rounded-xl text-sm transition-all duration-150 font-medium"
      :style="active
        ? 'background:rgba(37,99,235,.25);color:#ffffff;box-shadow:0 0 0 1px rgba(37,99,235,.3)'
        : 'color:#5b8dab'"
      :class="active ? '' : 'hover:bg-white/8 hover:text-white'">
      <div class="w-7 h-7 rounded-lg flex items-center justify-center flex-shrink-0 transition-all"
           :style="active
             ? 'background:rgba(37,99,235,.35)'
             : 'background:rgba(255,255,255,.05)'">
        <Icon :name="icon" class="w-3.5 h-3.5" />
      </div>
      <span class="flex-1 text-left text-[13px] leading-none">{{ label }}</span>
      <span v-if="badge > 0"
        class="min-w-[18px] h-[18px] bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center px-1 shadow-sm shadow-red-500/40">
        {{ badge > 99 ? '99+' : badge }}
      </span>
    </button>
  </router-link>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Icon from './Icon.vue'

const props = defineProps({
  to:    String,
  icon:  String,
  label: String,
  badge: { type: Number, default: 0 },
})

const route  = useRoute()
const active = computed(() => route.path === props.to || (props.to !== '/' && route.path.startsWith(props.to)))
</script>
