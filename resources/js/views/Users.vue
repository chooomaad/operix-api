<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-800">Utilisateurs</h2>
        <p class="text-sm text-gray-500 mt-0.5">Gérer les accès à la plateforme</p>
      </div>
      <button @click="openCreate" class="flex items-center gap-2 btn-primary">
        <Icon name="plus" class="w-4 h-4" /> Nouvel utilisateur
      </button>
    </div>

    <!-- Filtres -->
    <div class="flex gap-3 flex-wrap">
      <input v-model="params.search" @input="debouncedFetch" placeholder="Rechercher..." class="flex-1 min-w-48 px-3.5 py-2 rounded-lg border border-gray-300 bg-white text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" />
      <select v-model="params.role" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm">
        <option value="">Tous les rôles</option>
        <option value="admin">Admin</option>
        <option value="agent">Agent</option>
      </select>
      <select v-model="params.is_active" @change="fetch()" class="px-3 py-2 rounded-lg border border-gray-300 bg-white text-sm">
        <option value="">Tous les statuts</option>
        <option value="true">Actifs</option>
        <option value="false">Inactifs</option>
      </select>
    </div>

    <!-- Table -->
    <div class="card overflow-hidden">
      <div v-if="loading" class="flex justify-center py-16 text-gray-400">
        <svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
      </div>
      <table v-else class="w-full text-sm">
        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider border-b border-gray-200">
          <tr>
            <th class="px-5 py-3 text-left">Utilisateur</th>
            <th class="px-5 py-3 text-left">Matricule</th>
            <th class="px-5 py-3 text-left">Rôle</th>
            <th class="px-5 py-3 text-left">Statut</th>
            <th class="px-5 py-3 text-left">Créé le</th>
            <th class="px-5 py-3 text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-if="!items.length">
            <td colspan="6" class="px-5 py-12 text-center text-gray-400">Aucun utilisateur</td>
          </tr>
          <tr v-for="u in items" :key="u.id" class="hover:bg-gray-50 transition-colors">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-brand-600 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                  {{ u.name?.charAt(0)?.toUpperCase() }}
                </div>
                <div>
                  <p class="font-medium text-gray-800">{{ u.name }}</p>
                  <p class="text-xs text-gray-500">{{ u.email }}</p>
                </div>
              </div>
            </td>
            <td class="px-5 py-3 font-mono text-xs text-gray-500">{{ u.matricule || '—' }}</td>
            <td class="px-5 py-3">
              <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                :class="u.role === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'">
                {{ u.role === 'admin' ? 'Admin' : 'Agent' }}
              </span>
            </td>
            <td class="px-5 py-3">
              <span class="inline-flex items-center gap-1.5 text-xs font-medium"
                :class="u.is_active ? 'text-green-600' : 'text-gray-400'">
                <span class="w-1.5 h-1.5 rounded-full" :class="u.is_active ? 'bg-green-500' : 'bg-gray-300'"></span>
                {{ u.is_active ? 'Actif' : 'Inactif' }}
              </span>
            </td>
            <td class="px-5 py-3 text-xs text-gray-500">{{ u.created_at }}</td>
            <td class="px-5 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <button @click="openNotif(u)" title="Envoyer une notification" class="p-1.5 rounded hover:bg-gray-100 text-gray-400 hover:text-brand-600 transition"><Icon name="bell" class="w-4 h-4" /></button>
                <button @click="openEdit(u)" class="p-1.5 rounded hover:bg-gray-100 text-gray-400 hover:text-blue-600 transition"><Icon name="edit" class="w-4 h-4" /></button>
                <button @click="confirmDelete(u)" class="p-1.5 rounded hover:bg-gray-100 text-gray-400 hover:text-red-600 transition"><Icon name="trash-2" class="w-4 h-4" /></button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <Pagination :meta="meta" @change="goPage" />

    <!-- Modal create/edit -->
    <Modal v-model="showModal" :title="editing ? 'Modifier utilisateur' : 'Nouvel utilisateur'" size="md">
      <form @submit.prevent="save" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <Field label="Nom complet *" :error="errors.name" class="col-span-2">
            <input v-model="form.name" class="field" required />
          </Field>
          <Field label="Email *" :error="errors.email" class="col-span-2">
            <input v-model="form.email" type="email" class="field" required />
          </Field>
          <Field label="Rôle *" :error="errors.role">
            <select v-model="form.role" class="field" required>
              <option value="admin">Admin</option>
              <option value="agent">Agent</option>
            </select>
          </Field>
          <Field label="Matricule" :error="errors.matricule">
            <input v-model="form.matricule" class="field" />
          </Field>
          <Field label="Téléphone" :error="errors.phone">
            <input v-model="form.phone" class="field" />
          </Field>
          <Field label="Statut" :error="errors.is_active">
            <select v-model="form.is_active" class="field">
              <option :value="true">Actif</option>
              <option :value="false">Inactif</option>
            </select>
          </Field>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="showModal=false" class="btn-secondary">Annuler</button>
          <button type="submit" :disabled="saving" class="btn-primary">{{ saving ? '...' : 'Enregistrer' }}</button>
        </div>
      </form>
    </Modal>

    <!-- Modal envoyer notification -->
    <Modal v-model="showNotifModal" title="Envoyer une notification" size="md">
      <form @submit.prevent="sendNotif" class="space-y-4">
        <p class="text-sm text-gray-500">Destinataire : <span class="font-medium text-gray-800">{{ notifTarget?.name }}</span></p>
        <Field label="Titre *">
          <input v-model="notifForm.title" class="field" required />
        </Field>
        <Field label="Message *">
          <textarea v-model="notifForm.body" rows="3" class="field" required />
        </Field>
        <Field label="Type">
          <select v-model="notifForm.type" class="field">
            <option value="info">Info</option>
            <option value="success">Succès</option>
            <option value="warning">Avertissement</option>
            <option value="alert">Alerte</option>
          </select>
        </Field>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="showNotifModal=false" class="btn-secondary">Annuler</button>
          <button type="submit" :disabled="sendingNotif" class="btn-primary">{{ sendingNotif ? '...' : 'Envoyer' }}</button>
        </div>
      </form>
    </Modal>

    <ConfirmDialog v-model="showConfirm" message="Supprimer cet utilisateur ?" :loading="deleting" @confirm="doDelete" />
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useCrud } from '../composables/useCrud'
import api from '../api/client'
import Modal from '../components/ui/Modal.vue'
import Field from '../components/ui/Field.vue'
import Icon from '../components/ui/Icon.vue'
import Badge from '../components/ui/Badge.vue'
import Pagination from '../components/ui/Pagination.vue'
import ConfirmDialog from '../components/ui/ConfirmDialog.vue'

const { items, meta, loading, saving, deleting, errors, params, fetch, create, update, remove, goPage } = useCrud('/users')

const showModal = ref(false), showConfirm = ref(false), showNotifModal = ref(false)
const editing = ref(null), toDelete = ref(null), notifTarget = ref(null), sendingNotif = ref(false)

const form = reactive({ name: '', email: '', role: 'agent', matricule: '', phone: '', is_active: true })
const notifForm = reactive({ title: '', body: '', type: 'info' })

let debounce = null
function debouncedFetch() { clearTimeout(debounce); debounce = setTimeout(() => { params.page = 1; fetch() }, 350) }

function openCreate() { Object.assign(form, { name: '', email: '', role: 'agent', matricule: '', phone: '', is_active: true }); editing.value = null; showModal.value = true }
function openEdit(u) { Object.assign(form, { name: u.name, email: u.email, role: u.role, matricule: u.matricule || '', phone: u.phone || '', is_active: u.is_active }); editing.value = u; showModal.value = true }
async function save() { try { editing.value ? await update(editing.value.id, form) : await create(form); showModal.value = false } catch {} }

function openNotif(u) { notifTarget.value = u; Object.assign(notifForm, { title: '', body: '', type: 'info' }); showNotifModal.value = true }
async function sendNotif() {
  sendingNotif.value = true
  try {
    await api.post('/notifications', { ...notifForm, user_id: notifTarget.value.id })
    showNotifModal.value = false
  } catch {} finally { sendingNotif.value = false }
}

function confirmDelete(u) { toDelete.value = u; showConfirm.value = true }
async function doDelete() { await remove(toDelete.value.id); showConfirm.value = false }

fetch()
</script>
