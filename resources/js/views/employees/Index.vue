<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">{{ t('nav.employees') }}</h2>
      <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition">
        <Icon name="plus" class="w-4 h-4" /> {{ t('common.add') }}
      </button>
    </div>

    <div class="flex gap-3 flex-wrap">
      <input v-model="params.search" @input="debouncedFetch" :placeholder="t('common.search')" class="flex-1 min-w-48 px-3.5 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
      <select v-model="params.contract_type" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        <option value="">Tous types</option>
        <option v-for="c in ['CDI','CDD','Stage','Interim','Consultant']" :key="c" :value="c">{{ c }}</option>
      </select>
      <select v-model="params.status" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        <option value="">Tous statuts</option><option value="active">Actif</option><option value="inactive">Inactif</option>
      </select>
    </div>

    <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 overflow-hidden">
      <div v-if="loading" class="flex justify-center py-16 text-gray-400"><svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg></div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-surface-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
          <tr>
            <th class="px-4 py-3 text-left">Matricule</th><th class="px-4 py-3 text-left">Nom</th>
            <th class="px-4 py-3 text-left">Poste</th><th class="px-4 py-3 text-left">Contrat</th>
            <th class="px-4 py-3 text-left">Statut</th><th class="px-4 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
          <tr v-if="!items.length"><td colspan="6" class="px-4 py-12 text-center text-gray-400">Aucun employé trouvé</td></tr>
          <tr v-for="emp in items" :key="emp.id" class="hover:bg-gray-50 dark:hover:bg-surface-700/40 transition">
            <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ emp.matricule }}</td>
            <td class="px-4 py-3 font-medium">{{ emp.prenom }} {{ emp.nom }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ emp.poste }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ emp.contract_type }}</td>
            <td class="px-4 py-3"><Badge :value="emp.status" :label="emp.status === 'active' ? 'Actif' : 'Inactif'" /></td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click="router.push(`/employees/${emp.id}`)" class="p-1.5 rounded hover:bg-gray-100 text-gray-400 hover:text-indigo-600 transition" title="Voir profil"><Icon name="eye" class="w-4 h-4" /></button>
                <button @click="openEdit(emp)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-blue-600 transition"><Icon name="edit" class="w-4 h-4" /></button>
                <button @click="confirmDelete(emp)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-red-600 transition"><Icon name="trash-2" class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :meta="meta" @change="goPage" />

    <Modal v-model="showModal" :title="editing ? 'Modifier employé' : 'Nouvel employé'" size="lg">
      <form @submit.prevent="save" class="grid grid-cols-2 gap-4">
        <Field label="Prénom *" :error="errors.prenom"><input v-model="form.prenom" class="field" required /></Field>
        <Field label="Nom *" :error="errors.nom"><input v-model="form.nom" class="field" required /></Field>
        <Field label="Matricule" :error="errors.matricule"><input v-model="form.matricule" class="field" /></Field>
        <Field label="Poste" :error="errors.poste"><input v-model="form.poste" class="field" /></Field>
        <Field label="Genre" :error="errors.genre">
          <select v-model="form.genre" class="field"><option value="">—</option><option value="M">Masculin</option><option value="F">Féminin</option></select>
        </Field>
        <Field label="Type de contrat" :error="errors.contract_type">
          <select v-model="form.contract_type" class="field">
            <option value="">—</option>
            <option v-for="c in ['CDI','CDD','Stage','Interim','Consultant']" :key="c" :value="c">{{ c }}</option>
          </select>
        </Field>
        <Field label="Date d'embauche" :error="errors.hire_date"><input v-model="form.hire_date" type="date" class="field" /></Field>
        <Field label="Téléphone" :error="errors.phone"><input v-model="form.phone" class="field" /></Field>
        <Field label="Email" :error="errors.email"><input v-model="form.email" type="email" class="field" /></Field>
        <Field label="Statut" :error="errors.status">
          <select v-model="form.status" class="field"><option value="active">Actif</option><option value="inactive">Inactif</option></select>
        </Field>
        <div class="col-span-2 flex justify-end gap-3 pt-2">
          <button type="button" @click="showModal = false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-surface-600 hover:bg-gray-50 dark:hover:bg-surface-700 transition">{{ t('common.cancel') }}</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition disabled:opacity-60">{{ saving ? '...' : t('common.save') }}</button>
        </div>
      </form>
    </Modal>
    <ConfirmDialog v-model="showConfirm" message="Supprimer cet employé ?" :loading="deleting" @confirm="doDelete" />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { useCrud } from '../../composables/useCrud'
import Modal from '../../components/ui/Modal.vue'
import Badge from '../../components/ui/Badge.vue'
import Pagination from '../../components/ui/Pagination.vue'
import ConfirmDialog from '../../components/ui/ConfirmDialog.vue'
import Icon from '../../components/ui/Icon.vue'
import Field from '../../components/ui/Field.vue'

const { t } = useI18n()
const router = useRouter()
const { items, meta, loading, saving, deleting, errors, params, fetch, create, update, remove, goPage } = useCrud('/employees')

const showModal = ref(false), showConfirm = ref(false), editing = ref(null), toDelete = ref(null)
const form = reactive({ prenom:'', nom:'', matricule:'', poste:'', genre:'', contract_type:'', hire_date:'', phone:'', email:'', status:'active' })

let debounce = null
function debouncedFetch() { clearTimeout(debounce); debounce = setTimeout(() => { params.page = 1; fetch() }, 350) }
function openCreate() { Object.keys(form).forEach(k => form[k] = k === 'status' ? 'active' : ''); editing.value = null; showModal.value = true }
function openEdit(e) { Object.assign(form, { prenom: e.prenom, nom: e.nom, matricule: e.matricule||'', poste: e.poste||'', genre: e.genre||'', contract_type: e.contract_type||'', hire_date: e.hire_date||'', phone: e.phone||'', email: e.email||'', status: e.status }); editing.value = e; showModal.value = true }
async function save() { try { editing.value ? await update(editing.value.id, form) : await create(form); showModal.value = false } catch {} }
function confirmDelete(e) { toDelete.value = e; showConfirm.value = true }
async function doDelete() { await remove(toDelete.value.id); showConfirm.value = false }
fetch()
</script>
