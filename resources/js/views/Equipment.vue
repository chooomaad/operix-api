<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">{{ t('nav.equipment') }}</h2>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition"><Icon name="plus" class="w-4 h-4" /> {{ t('common.add') }}</button>
    </div>
    <div class="flex gap-3 flex-wrap">
      <input v-model="params.search" @input="debouncedFetch" :placeholder="t('common.search')" class="flex-1 min-w-48 px-3.5 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
      <select v-model="params.category" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm">
        <option value="">Toutes catégories</option>
        <option v-for="c in ['vehicle','crane','forklift','electrical','pressure','fire','ppe','tool','other']" :key="c" :value="c">{{ c }}</option>
      </select>
      <select v-model="params.status" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm">
        <option value="">Tous statuts</option>
        <option v-for="s in ['operational','maintenance','out_of_service','retired']" :key="s" :value="s">{{ t('status.'+s) }}</option>
      </select>
      <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" v-model="inspectionDue" @change="toggleInspection" class="rounded" /> À inspecter
      </label>
    </div>
    <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 overflow-hidden">
      <div v-if="loading" class="flex justify-center py-16 text-gray-400"><svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg></div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-surface-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
          <tr><th class="px-4 py-3 text-left">Code</th><th class="px-4 py-3 text-left">Nom</th><th class="px-4 py-3 text-left">Catégorie</th><th class="px-4 py-3 text-left">Prochaine inspection</th><th class="px-4 py-3 text-left">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
          <tr v-if="!items.length"><td colspan="6" class="px-4 py-12 text-center text-gray-400">Aucun équipement</td></tr>
          <tr v-for="eq in items" :key="eq.id" class="hover:bg-gray-50 dark:hover:bg-surface-700/40 transition">
            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ eq.code || '—' }}</td>
            <td class="px-4 py-3 font-medium">{{ eq.name }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300 capitalize">{{ eq.category }}</td>
            <td class="px-4 py-3 text-xs" :class="eq.inspection_due ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-gray-500'">
              {{ eq.next_inspection || '—' }}
              <span v-if="eq.inspection_due" class="ml-1 text-red-500">⚠</span>
            </td>
            <td class="px-4 py-3"><Badge :value="eq.status" :label="t('status.'+eq.status)" /></td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click="openInspect(eq)" title="Inspecter" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-green-600 transition"><Icon name="check" class="w-4 h-4" /></button>
                <button @click="openEdit(eq)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-blue-600 transition"><Icon name="edit" class="w-4 h-4" /></button>
                <button @click="confirmDelete(eq)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-red-600 transition"><Icon name="trash-2" class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :meta="meta" @change="goPage" />

    <Modal v-model="showModal" :title="editing ? 'Modifier équipement' : 'Nouvel équipement'" size="xl">
      <form @submit.prevent="save" class="grid grid-cols-2 gap-4">
        <Field label="Nom *" :error="errors.name"><input v-model="form.name" class="field" required /></Field>
        <Field label="Code" :error="errors.code"><input v-model="form.code" class="field" /></Field>
        <Field label="Catégorie *" :error="errors.category">
          <select v-model="form.category" class="field" required>
            <option value="">—</option>
            <option v-for="c in ['vehicle','crane','forklift','electrical','pressure','fire','ppe','tool','other']" :key="c" :value="c">{{ c }}</option>
          </select>
        </Field>
        <Field label="Statut" :error="errors.status">
          <select v-model="form.status" class="field">
            <option v-for="s in ['operational','maintenance','out_of_service','retired']" :key="s" :value="s">{{ t('status.'+s) }}</option>
          </select>
        </Field>
        <Field label="Marque" :error="errors.brand"><input v-model="form.brand" class="field" /></Field>
        <Field label="Modèle" :error="errors.model"><input v-model="form.model" class="field" /></Field>
        <Field label="N° série" :error="errors.serial_number"><input v-model="form.serial_number" class="field" /></Field>
        <Field label="Lieu" :error="errors.location"><input v-model="form.location" class="field" /></Field>
        <Field label="Dernière inspection" :error="errors.last_inspection"><input v-model="form.last_inspection" type="date" class="field" /></Field>
        <Field label="Prochaine inspection" :error="errors.next_inspection"><input v-model="form.next_inspection" type="date" class="field" /></Field>
        <Field label="Fréquence inspection (jours)" :error="errors.inspection_frequency_days"><input v-model.number="form.inspection_frequency_days" type="number" class="field" min="1" /></Field>
        <Field label="Notes" :error="errors.notes" class="col-span-2"><textarea v-model="form.notes" rows="2" class="field" /></Field>
        <div class="col-span-2 flex justify-end gap-3 pt-2">
          <button type="button" @click="showModal=false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-surface-600 hover:bg-gray-50 dark:hover:bg-surface-700 transition">{{ t('common.cancel') }}</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition disabled:opacity-60">{{ saving ? '...' : t('common.save') }}</button>
        </div>
      </form>
    </Modal>

    <Modal v-model="showInspect" :title="`Inspecter : ${inspecting?.name}`" size="md">
      <form @submit.prevent="doInspect" class="space-y-4">
        <Field label="Date *" :error="{}"><input v-model="inspectForm.date" type="date" class="field" required /></Field>
        <div class="grid grid-cols-2 gap-4">
          <Field label="Type"><select v-model="inspectForm.type" class="field"><option v-for="v in ['periodic','pre_use','post_incident','regulatory']" :key="v" :value="v">{{ v }}</option></select></Field>
          <Field label="Résultat *"><select v-model="inspectForm.result" class="field" required><option value="pass">Pass</option><option value="fail">Fail</option><option value="conditional">Conditionnel</option></select></Field>
        </div>
        <Field label="Inspecteur *"><input v-model="inspectForm.inspector" class="field" required /></Field>
        <Field label="Observations"><textarea v-model="inspectForm.observations" rows="2" class="field" /></Field>
        <div class="flex justify-end gap-3">
          <button type="button" @click="showInspect=false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-surface-600 hover:bg-gray-50 dark:hover:bg-surface-700 transition">{{ t('common.cancel') }}</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 text-sm rounded-lg bg-green-600 hover:bg-green-700 text-white font-medium transition disabled:opacity-60">{{ saving ? '...' : 'Valider' }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-model="showConfirm" message="Supprimer cet équipement ?" :loading="deleting" @confirm="doDelete" />
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
const { items, meta, loading, saving, deleting, errors, params, fetch, create, update, remove, action, goPage } = useCrud('/equipment')
const showModal=ref(false), showConfirm=ref(false), showInspect=ref(false)
const editing=ref(null), toDelete=ref(null), inspecting=ref(null), inspectionDue=ref(false)
const form = reactive({ name:'', code:'', category:'', status:'operational', brand:'', model:'', serial_number:'', location:'', last_inspection:'', next_inspection:'', inspection_frequency_days:'', notes:'' })
const inspectForm = reactive({ date: new Date().toISOString().slice(0,10), type:'periodic', result:'pass', inspector:'', observations:'' })
let debounce=null
function debouncedFetch(){ clearTimeout(debounce); debounce=setTimeout(()=>{ params.page=1; fetch() },350) }
function toggleInspection(){ params.inspection_due = inspectionDue.value ? 1 : ''; fetch() }
function openCreate(){ Object.keys(form).forEach(k=>form[k]=k==='status'?'operational':''); editing.value=null; showModal.value=true }
function openEdit(e){ Object.assign(form,{ name:e.name, code:e.code||'', category:e.category, status:e.status, brand:e.brand||'', model:e.model||'', serial_number:e.serial_number||'', location:e.location||'', last_inspection:e.last_inspection||'', next_inspection:e.next_inspection||'', inspection_frequency_days:e.inspection_frequency_days||'', notes:e.notes||'' }); editing.value=e; showModal.value=true }
async function save(){ try{ editing.value ? await update(editing.value.id,form) : await create(form); showModal.value=false }catch{} }
function openInspect(e){ inspecting.value=e; inspectForm.date=new Date().toISOString().slice(0,10); inspectForm.type='periodic'; inspectForm.result='pass'; inspectForm.inspector=''; inspectForm.observations=''; showInspect.value=true }
async function doInspect(){ try{ await action(`/equipment/${inspecting.value.id}/inspect`, inspectForm); showInspect.value=false }catch{} }
function confirmDelete(e){ toDelete.value=e; showConfirm.value=true }
async function doDelete(){ await remove(toDelete.value.id); showConfirm.value=false }
fetch()
</script>
