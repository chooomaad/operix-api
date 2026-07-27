<template>
  <Modal :modelValue="modelValue" :title="title || t('common.confirm_delete')" size="sm" @update:modelValue="$emit('update:modelValue', $event)">
    <p class="text-sm text-gray-600 dark:text-gray-300">{{ message }}</p>
    <template #footer>
      <div class="flex gap-3 justify-end">
        <button @click="$emit('update:modelValue', false)" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-surface-600 hover:bg-gray-50 dark:hover:bg-surface-700 transition">
          {{ t('common.cancel') }}
        </button>
        <button @click="$emit('confirm')" :disabled="loading" class="px-4 py-2 text-sm rounded-lg bg-red-600 hover:bg-red-700 text-white font-medium transition disabled:opacity-60">
          {{ loading ? '...' : t('common.yes_delete') }}
        </button>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import { useI18n } from 'vue-i18n'
import Modal from './Modal.vue'

const { t } = useI18n()
defineProps({ modelValue: Boolean, title: String, message: String, loading: Boolean })
defineEmits(['update:modelValue', 'confirm'])
</script>
