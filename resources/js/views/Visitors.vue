<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <h2 class="text-xl font-bold">{{ t('nav.visitors') }}</h2>
      <div class="flex items-center gap-2">
        <span class="px-3 py-1.5 bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-400 text-sm font-medium rounded-full">{{ onSiteCount }} présent(s)</span>
        <button @click="openCreate" class="flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg transition"><Icon name="plus" class="w-4 h-4" /> Check-in</button>
      </div>
    </div>
    <div class="flex gap-3 flex-wrap">
      <input v-model="params.search" @input="debouncedFetch" :placeholder="t('common.search')" class="flex-1 min-w-48 px-3.5 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
      <select v-model="params.status" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 dark:border-surface-600 bg-white dark:bg-surface-800 text-sm">
        <option value="">Tous</option><option value="in">Présents</option><option value="out">Sortis</option>
      </select>
    </div>
    <div class="bg-white dark:bg-surface-800 rounded-xl border border-gray-200 dark:border-surface-700 overflow-hidden">
      <div v-if="loading" class="flex justify-center py-16 text-gray-400"><svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg></div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-surface-700/50 text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider">
          <tr><th class="px-4 py-3 text-left">Visiteur</th><th class="px-4 py-3 text-left">Entreprise</th><th class="px-4 py-3 text-left">Motif</th><th class="px-4 py-3 text-left">Arrivée</th><th class="px-4 py-3 text-left">Durée</th><th class="px-4 py-3 text-left">Statut</th><th class="px-4 py-3 text-right">Actions</th></tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-surface-700">
          <tr v-if="!items.length"><td colspan="7" class="px-4 py-12 text-center text-gray-400">Aucun visiteur</td></tr>
          <tr v-for="v in items" :key="v.id" class="hover:bg-gray-50 dark:hover:bg-surface-700/40 transition">
            <td class="px-4 py-3 font-medium">{{ v.full_name }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ v.entreprise || '—' }}</td>
            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ v.motif }}</td>
            <td class="px-4 py-3 text-gray-500 text-xs">{{ v.checked_in_at }}</td>
            <td class="px-4 py-3 text-gray-500 text-xs">{{ v.duration || '—' }}</td>
            <td class="px-4 py-3"><Badge :value="v.status" :label="v.status === 'in' ? 'Présent' : 'Sorti'" /></td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button v-if="v.status==='in'" @click="doCheckout(v)" title="Check-out" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-teal-600 transition"><Icon name="log-out" class="w-4 h-4" /></button>
                <button @click="confirmDelete(v)" class="p-1.5 rounded hover:bg-gray-100 dark:hover:bg-surface-700 text-gray-400 hover:text-red-600 transition"><Icon name="trash-2" class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :meta="meta" @change="goPage" />

    <Modal v-model="showModal" title="Check-in visiteur" size="lg">
      <form @submit.prevent="save" class="grid grid-cols-2 gap-4">
        <Field label="Prénom *" :error="errors.prenom"><input v-model="form.prenom" class="field" required /></Field>
        <Field label="Nom *" :error="errors.nom"><input v-model="form.nom" class="field" required /></Field>
        <Field label="Entreprise" :error="errors.entreprise"><input v-model="form.entreprise" class="field" /></Field>
        <Field label="Téléphone" :error="errors.phone"><input v-model="form.phone" class="field" /></Field>
        <Field label="Motif *" :error="errors.motif" class="col-span-2"><input v-model="form.motif" class="field" required /></Field>
        <Field label="Personne visitée" :error="errors.personne_visitee"><input v-model="form.personne_visitee" class="field" /></Field>
        <Field label="Badge n°" :error="errors.badge_number"><input v-model="form.badge_number" class="field" /></Field>
        <Field label="Plaque véhicule" :error="errors.vehicle_plate"><input v-model="form.vehicle_plate" class="field" /></Field>
        <div class="col-span-2 flex justify-end gap-3 pt-2">
          <button type="button" @click="showModal=false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 dark:border-surface-600 hover:bg-gray-50 dark:hover:bg-surface-700 transition">{{ t('common.cancel') }}</button>
          <button type="submit" :disabled="saving" class="px-4 py-2 text-sm rounded-lg bg-brand-600 hover:bg-brand-700 text-white font-medium transition disabled:opacity-60">{{ saving ? '...' : 'Enregistrer' }}</button>
        </div>
      </form>
    </Modal>
    <ConfirmDialog v-model="showConfirm" message="Supprimer cette visite ?" :loading="deleting" @confirm="doDelete" />
  </div>
</template>
<script setup>
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useCrud } from '../composables/useCrud'
import api from '../api/client'
import Modal from '../components/ui/Modal.vue'
import Badge from '../components/ui/Badge.vue'
import Pagination from '../components/ui/Pagination.vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'
import Icon from '../components/ui/Icon.vue'
import Field from '../components/ui/Field.vue'
const { t } = useI18n()
const { items, meta, loading, saving, deleting, errors, params, fetch, create, remove, goPage } = useCrud('/visitors')
const showModal=ref(false), showConfirm=ref(false), toDelete=ref(null), onSiteCount=ref(0)
const form = reactive({ prenom:'', nom:'', entreprise:'', phone:'', motif:'', personne_visitee:'', badge_number:'', vehicle_plate:'' })
let debounce=null
function debouncedFetch(){ clearTimeout(debounce); debounce=setTimeout(()=>{ params.page=1; fetch() },350) }
function openCreate(){ Object.keys(form).forEach(k=>form[k]=''); showModal.value=true }
async function save(){ try{ await create(form); showModal.value=false; loadOnSite() }catch{} }
async function doCheckout(v){ await api.post(`/visitors/${v.id}/checkout`); fetch(); loadOnSite() }
function confirmDelete(v){ toDelete.value=v; showConfirm.value=true }
async function doDelete(){ await remove(toDelete.value.id); showConfirm.value=false; loadOnSite() }
async function loadOnSite(){ const { data } = await api.get('/visitors/on-site'); onSiteCount.value = data.count }
fetch(); loadOnSite()
</script>
