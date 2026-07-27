<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">{{ t('nav.environment') }}</h2>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition"><Icon name="plus" class="w-4 h-4" /> {{ t('common.add') }}</button>
    </div>
    <div class="flex gap-3 flex-wrap">
      <input v-model="params.search" @input="debouncedFetch" :placeholder="t('common.search')" class="flex-1 min-w-48 px-3.5 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
      <select v-model="params.type" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm">
        <option value="">Tous types</option>
        <option v-for="v in ['spill','emission','waste','noise','other']" :key="v" :value="v">{{ v }}</option>
      </select>
      <select v-model="params.status" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm">
        <option value="">Tous statuts</option><option v-for="s in ['open','in_progress','closed']" :key="s" :value="s">{{ t('status.'+s) }}</option>
      </select>
    </div>
    <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 overflow-hidden">
      <div v-if="loading" class="flex justify-center py-16 text-gray-400"><svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg></div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-surface-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
          <tr><th class="px-4 py-3 text-left">Réf.</th><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Sévérité</th><th class="px-4 py-3 text-left">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
          <tr v-if="!items.length"><td colspan="6" class="px-4 py-12 text-center text-gray-400">Aucun rapport</td></tr>
          <tr v-for="env in items" :key="env.id" class="hover:bg-gray-50 dark:hover:bg-surface-700/40 transition">
            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ env.reference }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ env.date }}</td>
            <td class="px-4 py-3 font-medium capitalize">{{ env.type }}</td>
            <td class="px-4 py-3"><Badge :value="env.severity" :label="t('severity.'+env.severity)" /></td>
            <td class="px-4 py-3"><Badge :value="env.status" :label="t('status.'+env.status)" /></td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click="openEdit(env)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-blue-600 transition"><Icon name="edit" class="w-4 h-4" /></button>
                <button @click="confirmDelete(env)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-red-600 transition"><Icon name="trash-2" class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :meta="meta" @change="goPage" />
    <Modal v-model="showModal" :title="editing ? 'Modifier rapport' : 'Nouveau rapport environnement'" size="lg">
      <form @submit.prevent="save" class="grid grid-cols-2 gap-4">
        <Field label="Date *" :error="errors.date"><input v-model="form.date" type="date" class="field" required /></Field>
        <Field label="Lieu *" :error="errors.location"><input v-model="form.location" class="field" required /></Field>
        <Field label="Type *" :error="errors.type">
          <select v-model="form.type" class="field" required><option value="">—</option><option v-for="v in ['spill','emission','waste','noise','other']" :key="v" :value="v">{{ v }}</option></select>
        </Field>
        <Field label="Sévérité *" :error="errors.severity">
          <select v-model="form.severity" class="field" required><option value="">—</option><option v-for="s in ['low','medium','high','critical']" :key="s" :value="s">{{ t('severity.'+s) }}</option></select>
        </Field>
        <Field label="Statut" :error="errors.status">
          <select v-model="form.status" class="field"><option v-for="s in ['open','in_progress','closed']" :key="s" :value="s">{{ t('status.'+s) }}</option></select>
        </Field>
        <Field label="Impact" :error="errors.impact"><input v-model="form.impact" class="field" /></Field>
        <Field label="Description *" :error="errors.description" class="col-span-2"><textarea v-model="form.description" rows="3" class="field" required /></Field>
        <Field label="Action corrective" :error="errors.corrective_action" class="col-span-2"><textarea v-model="form.corrective_action" rows="2" class="field" /></Field>
        <div class="col-span-2 flex justify-end gap-3 pt-2">
          <button type="button" @click="showModal=false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-surface-600 hover:bg-gray-50 dark:hover:bg-surface-700 transition">{{ t('common.cancel') }}</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition disabled:opacity-60">{{ saving ? '...' : t('common.save') }}</button>
        </div>
      </form>
    </Modal>
    <ConfirmDialog v-model="showConfirm" message="Supprimer ce rapport ?" :loading="deleting" @confirm="doDelete" />
  </div>
</template>
<script setup>
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCrud } from '../../composables/useCrud'
import Modal from '../../components/ui/Modal.vue'
import Badge from '../../components/ui/Badge.vue'
import Pagination from '../../components/ui/Pagination.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import Icon from '../../components/ui/Icon.vue'
import Field from '../../components/ui/Field.vue'
const { t } = useI18n()
const { items, meta, loading, saving, deleting, errors, params, fetch, create, update, remove, goPage } = useCrud('/environment')
const showModal=ref(false), showConfirm=ref(false), editing=ref(null), toDelete=ref(null)
const form = reactive({ date:'', location:'', type:'', severity:'', status:'open', impact:'', description:'', corrective_action:'' })
let debounce=null
function debouncedFetch(){ clearTimeout(debounce); debounce=setTimeout(()=>{ params.page=1; fetch() },350) }
function openCreate(){ Object.keys(form).forEach(k=>form[k]=k==='status'?'open':''); editing.value=null; showModal.value=true }
function openEdit(e){ Object.assign(form,{ date:e.date, location:e.location, type:e.type, severity:e.severity, status:e.status, impact:e.impact||'', description:e.description||'', corrective_action:e.corrective_action||'' }); editing.value=e; showModal.value=true }
async function save(){ try{ editing.value ? await update(editing.value.id,form) : await create(form); showModal.value=false }catch{} }
function confirmDelete(e){ toDelete.value=e; showConfirm.value=true }
async function doDelete(){ await remove(toDelete.value.id); showConfirm.value=false }
fetch()
</script>
