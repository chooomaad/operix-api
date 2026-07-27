<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <!-- Overlay -->
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$emit('update:modelValue', false)" />
        <!-- Panel -->
        <div
          class="relative w-full rounded-2xl shadow-2xl flex flex-col max-h-[90vh] transition-colors"
          :class="[sizeClass, dark
            ? 'bg-surface-800 border border-surface-700'
            : 'bg-white border border-gray-200']">
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 shrink-0"
               :class="dark ? 'border-b border-surface-700' : 'border-b border-gray-100'">
            <h3 class="text-base font-semibold" :class="dark ? 'text-white' : 'text-gray-900'">{{ title }}</h3>
            <button @click="$emit('update:modelValue', false)"
                    class="p-1.5 rounded-lg transition"
                    :class="dark
                      ? 'hover:bg-surface-700 text-surface-400 hover:text-white'
                      : 'hover:bg-gray-100 text-gray-400 hover:text-gray-600'">
              <Icon name="x" class="w-4 h-4" />
            </button>
          </div>
          <!-- Body -->
          <div class="overflow-y-auto flex-1 px-6 py-5">
            <slot />
          </div>
          <!-- Footer -->
          <div v-if="$slots.footer" class="px-6 py-4 shrink-0"
               :class="dark ? 'border-t border-surface-700' : 'border-t border-gray-100'">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed } from 'vue'
import Icon from './Icon.vue'
import { useThemeStore } from '../../stores/theme'

const props = defineProps({
  modelValue: Boolean,
  title: String,
  size: { type: String, default: 'md' },
})
defineEmits(['update:modelValue'])

const { dark } = useThemeStore()

const sizeClass = computed(() => ({
  sm: 'max-w-sm',
  md: 'max-w-lg',
  lg: 'max-w-2xl',
  xl: 'max-w-4xl',
}[props.size] || 'max-w-lg'))
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity .15s ease, transform .15s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; transform: scale(.97); }
</style>
