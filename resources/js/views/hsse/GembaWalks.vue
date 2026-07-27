<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">{{ t('nav.gemba_walks') }}</h2>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition"><Icon name="plus" class="w-4 h-4" /> {{ t('common.add') }}</button>
    </div>
    <div class="flex gap-3 flex-wrap">
      <input v-model="params.search" @input="debouncedFetch" :placeholder="t('common.search')" class="flex-1 min-w-48 px-3.5 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
      <select v-model="params.priority" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm">
        <option value="">Toutes priorités</option><option v-for="p in ['low','medium','high']" :key="p" :value="p">{{ t('severity.'+p) }}</option>
      </select>
      <select v-model="params.status" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm">
        <option value="">Tous statuts</option><option v-for="s in ['open','in_progress','resolved']" :key="s" :value="s">{{ t('status.'+s) }}</option>
      </select>
      <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" v-model="overdue" @change="toggleOverdue" class="rounded" /> En retard
      </label>
    </div>
    <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 overflow-hidden">
      <div v-if="loading" class="flex justify-center py-16 text-gray-400"><svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg></div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-surface-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
          <tr><th class="px-4 py-3 text-left">Zone</th><th class="px-4 py-3 text-left">Auditeur</th><th class="px-4 py-3 text-left">Responsable</th><th class="px-4 py-3 text-left">Échéance</th><th class="px-4 py-3 text-left">Priorité</th><th class="px-4 py-3 text-left">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
          <tr v-if="!items.length"><td colspan="7" class="px-4 py-12 text-center text-gray-400">Aucun Gemba Walk</td></tr>
          <tr v-for="gw in items" :key="gw.id" class="hover:bg-gray-50 dark:hover:bg-surface-700/40 transition" :class="gw.is_overdue ? 'bg-red-50/30 dark:bg-red-900/10' : ''">
            <td class="px-4 py-3 font-medium">{{ gw.zone }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ gw.auditor }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ gw.responsible }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300" :class="gw.is_overdue ? 'text-red-600 dark:text-red-400 font-semibold' : ''">{{ gw.due_date }}</td>
            <td class="px-4 py-3"><Badge :value="gw.priority" :label="t('severity.'+gw.priority)" /></td>
            <td class="px-4 py-3"><Badge :value="gw.status" :label="t('status.'+gw.status)" /></td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button v-if="gw.status!=='resolved'" @click="resolveGemba(gw)" title="Résoudre" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-green-600 transition"><Icon name="check" class="w-4 h-4" /></button>
                <button @click="openEdit(gw)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-blue-600 transition"><Icon name="edit" class="w-4 h-4" /></button>
                <button @click="confirmDelete(gw)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-red-600 transition"><Icon name="trash-2" class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :meta="meta" @change="goPage" />
    <Modal v-model="showModal" :title="editing ? 'Modifier Gemba Walk' : 'Nouveau Gemba Walk'" size="lg">
      <form @submit.prevent="save" class="grid grid-cols-2 gap-4">
        <Field label="Zone *" :error="errors.zone"><input v-model="form.zone" class="field" required /></Field>
        <Field label="Auditeur *" :error="errors.auditor"><input v-model="form.auditor" class="field" required /></Field>
        <Field label="Responsable" :error="errors.responsible"><input v-model="form.responsible" class="field" /></Field>
        <Field label="Échéance" :error="errors.due_date"><input v-model="form.due_date" type="date" class="field" /></Field>
        <Field label="Priorité" :error="errors.priority">
          <select v-model="form.priority" class="field"><option value="">—</option><option v-for="p in ['low','medium','high']" :key="p" :value="p">{{ t('severity.'+p) }}</option></select>
        </Field>
        <Field label="Statut" :error="errors.status">
          <select v-model="form.status" class="field"><option v-for="s in ['open','in_progress','resolved']" :key="s" :value="s">{{ t('status.'+s) }}</option></select>
        </Field>
        <Field label="Observation *" :error="errors.observation" class="col-span-2"><textarea v-model="form.observation" rows="3" class="field" required /></Field>
        <Field label="Action requise" :error="errors.action_required" class="col-span-2"><textarea v-model="form.action_required" rows="2" class="field" /></Field>
        <div class="col-span-2 flex justify-end gap-3 pt-2">
          <button type="button" @click="showModal=false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-surface-600 hover:bg-gray-50 dark:hover:bg-surface-700 transition">{{ t('common.cancel') }}</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition disabled:opacity-60">{{ saving ? '...' : t('common.save') }}</button>
        </div>
      </form>
    </Modal>
    <ConfirmDialog v-model="showConfirm" message="Supprimer ce Gemba Walk ?" :loading="deleting" @confirm="doDelete" />
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
const { items, meta, loading, saving, deleting, errors, params, fetch, create, update, remove, action, goPage } = useCrud('/gemba-walks')
const showModal=ref(false), showConfirm=ref(false), editing=ref(null), toDelete=ref(null), overdue=ref(false)
const form = reactive({ zone:'', auditor:'', observation:'', action_required:'', responsible:'', due_date:'', priority:'', status:'open' })
let debounce=null
function debouncedFetch(){ clearTimeout(debounce); debounce=setTimeout(()=>{ params.page=1; fetch() },350) }
function toggleOverdue(){ params.overdue = overdue.value ? 1 : ''; fetch() }
function openCreate(){ Object.keys(form).forEach(k=>form[k]=k==='status'?'open':''); editing.value=null; showModal.value=true }
function openEdit(g){ Object.assign(form,{ zone:g.zone, auditor:g.auditor, observation:g.observation||'', action_required:g.action_required||'', responsible:g.responsible||'', due_date:g.due_date||'', priority:g.priority||'', status:g.status }); editing.value=g; showModal.value=true }
async function save(){ try{ editing.value ? await update(editing.value.id,form) : await create(form); showModal.value=false }catch{} }
async function resolveGemba(g){ await action(`/gemba-walks/${g.id}/resolve`) }
function confirmDelete(g){ toDelete.value=g; showConfirm.value=true }
async function doDelete(){ await remove(toDelete.value.id); showConfirm.value=false }
fetch()
</script>
