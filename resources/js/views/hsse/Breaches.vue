<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">{{ t('nav.breaches') }}</h2>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition"><Icon name="plus" class="w-4 h-4" /> {{ t('common.add') }}</button>
    </div>
    <div class="flex gap-3 flex-wrap">
      <input v-model="params.search" @input="debouncedFetch" :placeholder="t('common.search')" class="flex-1 min-w-48 px-3.5 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
      <select v-model="params.severity" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm">
        <option value="">Toutes sanctions</option>
        <option v-for="s in ['avertissement','blame','mise_a_pied','licenciement']" :key="s" :value="s">{{ s.replace('_',' ') }}</option>
      </select>
    </div>
    <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 overflow-hidden">
      <div v-if="loading" class="flex justify-center py-16 text-gray-400"><svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg></div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-surface-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
          <tr><th class="px-4 py-3 text-left">Réf.</th><th class="px-4 py-3 text-left">Employé</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-left">Sanction</th><th class="px-4 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
          <tr v-if="!items.length"><td colspan="5" class="px-4 py-12 text-center text-gray-400">Aucune infraction</td></tr>
          <tr v-for="br in items" :key="br.id" class="hover:bg-gray-50 dark:hover:bg-surface-700/40 transition">
            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ br.reference }}</td>
            <td class="px-4 py-3 font-medium">{{ br.employee?.prenom }} {{ br.employee?.nom }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ br.type }}</td>
            <td class="px-4 py-3"><Badge :value="br.severity" :label="br.severity?.replace('_',' ')" /></td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click="openEdit(br)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-blue-600 transition"><Icon name="edit" class="w-4 h-4" /></button>
                <button @click="confirmDelete(br)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-red-600 transition"><Icon name="trash-2" class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :meta="meta" @change="goPage" />
    <Modal v-model="showModal" :title="editing ? 'Modifier infraction' : 'Nouvelle infraction'" size="lg">
      <form @submit.prevent="save" class="grid grid-cols-2 gap-4">
        <Field label="ID Employé *" :error="errors.employee_id"><input v-model.number="form.employee_id" type="number" class="field" required placeholder="ID numérique" /></Field>
        <Field label="Type *" :error="errors.type"><input v-model="form.type" class="field" required /></Field>
        <Field label="Sanction *" :error="errors.severity">
          <select v-model="form.severity" class="field" required>
            <option value="">—</option>
            <option v-for="s in ['avertissement','blame','mise_a_pied','licenciement']" :key="s" :value="s">{{ s.replace('_',' ') }}</option>
          </select>
        </Field>
        <Field label="Sanction texte" :error="errors.sanction"><input v-model="form.sanction" class="field" /></Field>
        <Field label="Description *" :error="errors.description" class="col-span-2"><textarea v-model="form.description" rows="3" class="field" required /></Field>
        <div class="col-span-2 flex justify-end gap-3 pt-2">
          <button type="button" @click="showModal=false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-surface-600 hover:bg-gray-50 dark:hover:bg-surface-700 transition">{{ t('common.cancel') }}</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition disabled:opacity-60">{{ saving ? '...' : t('common.save') }}</button>
        </div>
      </form>
    </Modal>
    <ConfirmDialog v-model="showConfirm" message="Supprimer cette infraction ?" :loading="deleting" @confirm="doDelete" />
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
const { items, meta, loading, saving, deleting, errors, params, fetch, create, update, remove, goPage } = useCrud('/breaches')
const showModal=ref(false), showConfirm=ref(false), editing=ref(null), toDelete=ref(null)
const form = reactive({ employee_id:'', type:'', severity:'', description:'', sanction:'' })
let debounce=null
function debouncedFetch(){ clearTimeout(debounce); debounce=setTimeout(()=>{ params.page=1; fetch() },350) }
function openCreate(){ Object.keys(form).forEach(k=>form[k]=''); editing.value=null; showModal.value=true }
function openEdit(b){ Object.assign(form,{ employee_id:b.employee_id, type:b.type, severity:b.severity, description:b.description||'', sanction:b.sanction||'' }); editing.value=b; showModal.value=true }
async function save(){ try{ editing.value ? await update(editing.value.id,form) : await create(form); showModal.value=false }catch{} }
function confirmDelete(b){ toDelete.value=b; showConfirm.value=true }
async function doDelete(){ await remove(toDelete.value.id); showConfirm.value=false }
fetch()
</script>
