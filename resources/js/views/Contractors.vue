<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">{{ t('nav.contractors') }}</h2>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition"><Icon name="plus" class="w-4 h-4" /> {{ t('common.add') }}</button>
    </div>
    <div class="flex gap-3 flex-wrap">
      <input v-model="params.search" @input="debouncedFetch" :placeholder="t('common.search')" class="flex-1 min-w-48 px-3.5 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
      <select v-model="params.status" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm">
        <option value="">Tous statuts</option>
        <option v-for="s in ['active','suspended','expired']" :key="s" :value="s">{{ t('status.'+s) }}</option>
      </select>
      <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" v-model="expiringSoon" @change="toggleExpiring" class="rounded" /> Expiration proche
      </label>
    </div>
    <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 overflow-hidden">
      <div v-if="loading" class="flex justify-center py-16 text-gray-400"><svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg></div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-surface-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
          <tr><th class="px-4 py-3 text-left">Société</th><th class="px-4 py-3 text-left">Activité</th><th class="px-4 py-3 text-left">Contact</th><th class="px-4 py-3 text-left">Fin contrat</th><th class="px-4 py-3 text-left">Employés</th><th class="px-4 py-3 text-left">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
          <tr v-if="!items.length"><td colspan="7" class="px-4 py-12 text-center text-gray-400">Aucun sous-traitant</td></tr>
          <tr v-for="c in items" :key="c.id" class="hover:bg-gray-50 dark:hover:bg-surface-700/40 transition">
            <td class="px-4 py-3 font-medium">{{ c.company_name }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ c.activite }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ c.contact_nom || '—' }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300" :class="c.is_expired ? 'text-red-600 dark:text-red-400' : ''">{{ c.contract_end || '—' }}</td>
            <td class="px-4 py-3 text-center text-gray-600 dark:text-gray-300">{{ c.employees_count }}</td>
            <td class="px-4 py-3"><Badge :value="c.status" :label="t('status.'+c.status)" /></td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click="openEdit(c)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-blue-600 transition"><Icon name="edit" class="w-4 h-4" /></button>
                <button @click="confirmDelete(c)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-red-600 transition"><Icon name="trash-2" class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :meta="meta" @change="goPage" />
    <Modal v-model="showModal" :title="editing ? 'Modifier sous-traitant' : 'Nouveau sous-traitant'" size="lg">
      <form @submit.prevent="save" class="grid grid-cols-2 gap-4">
        <Field label="Société *" :error="errors.company_name" class="col-span-2"><input v-model="form.company_name" class="field" required /></Field>
        <Field label="Activité *" :error="errors.activite" class="col-span-2"><input v-model="form.activite" class="field" required /></Field>
        <Field label="Contact nom" :error="errors.contact_nom"><input v-model="form.contact_nom" class="field" /></Field>
        <Field label="Contact tél" :error="errors.contact_phone"><input v-model="form.contact_phone" class="field" /></Field>
        <Field label="Contact email" :error="errors.contact_email"><input v-model="form.contact_email" type="email" class="field" /></Field>
        <Field label="N° registre" :error="errors.num_registre"><input v-model="form.num_registre" class="field" /></Field>
        <Field label="Début contrat" :error="errors.contract_start"><input v-model="form.contract_start" type="date" class="field" /></Field>
        <Field label="Fin contrat" :error="errors.contract_end"><input v-model="form.contract_end" type="date" class="field" /></Field>
        <Field label="Statut" :error="errors.status">
          <select v-model="form.status" class="field"><option v-for="s in ['active','suspended','expired']" :key="s" :value="s">{{ t('status.'+s) }}</option></select>
        </Field>
        <Field label="Zones autorisées" :error="errors.zones_autorisees"><input v-model="form.zones_autorisees" class="field" /></Field>
        <Field label="Notes" :error="errors.notes" class="col-span-2"><textarea v-model="form.notes" rows="2" class="field" /></Field>
        <div class="col-span-2 flex justify-end gap-3 pt-2">
          <button type="button" @click="showModal=false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-surface-600 hover:bg-gray-50 dark:hover:bg-surface-700 transition">{{ t('common.cancel') }}</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition disabled:opacity-60">{{ saving ? '...' : t('common.save') }}</button>
        </div>
      </form>
    </Modal>
    <ConfirmDialog v-model="showConfirm" message="Supprimer ce sous-traitant ?" :loading="deleting" @confirm="doDelete" />
  </div>
</template>
<script setup>
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCrud } from '../composables/useCrud'
import Modal from '../components/ui/Modal.vue'
import Badge from '../components/ui/Badge.vue'
import Pagination from '../components/ui/Pagination.vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import Icon from '../components/ui/Icon.vue'
import Field from '../components/ui/Field.vue'
const { t } = useI18n()
const { items, meta, loading, saving, deleting, errors, params, fetch, create, update, remove, goPage } = useCrud('/contractors')
const showModal=ref(false), showConfirm=ref(false), editing=ref(null), toDelete=ref(null), expiringSoon=ref(false)
const form = reactive({ company_name:'', activite:'', contact_nom:'', contact_phone:'', contact_email:'', num_registre:'', contract_start:'', contract_end:'', status:'active', zones_autorisees:'', notes:'' })
let debounce=null
function debouncedFetch(){ clearTimeout(debounce); debounce=setTimeout(()=>{ params.page=1; fetch() },350) }
function toggleExpiring(){ params.expiring_soon = expiringSoon.value ? 1 : ''; fetch() }
function openCreate(){ Object.keys(form).forEach(k=>form[k]=k==='status'?'active':''); editing.value=null; showModal.value=true }
function openEdit(c){ Object.assign(form,{ company_name:c.company_name, activite:c.activite, contact_nom:c.contact_nom||'', contact_phone:c.contact_phone||'', contact_email:c.contact_email||'', num_registre:c.num_registre||'', contract_start:c.contract_start||'', contract_end:c.contract_end||'', status:c.status, zones_autorisees:c.zones_autorisees||'', notes:c.notes||'' }); editing.value=c; showModal.value=true }
async function save(){ try{ editing.value ? await update(editing.value.id,form) : await create(form); showModal.value=false }catch{} }
function confirmDelete(c){ toDelete.value=c; showConfirm.value=true }
async function doDelete(){ await remove(toDelete.value.id); showConfirm.value=false }
fetch()
</script>
