<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h2 class="text-xl font-bold text-gray-800">Notifications</h2>
        <p class="text-sm text-gray-500 mt-0.5">{{ isAdmin ? 'Gérer et envoyer des notifications' : 'Vos notifications' }}</p>
      </div>
      <div class="flex gap-2">
        <button @click="markAll" class="btn-secondary text-sm">Tout marquer lu</button>
        <button v-if="isAdmin" @click="showSend = true" class="btn-primary flex items-center gap-2">
          <Icon name="bell" class="w-4 h-4" /> Envoyer une notification
        </button>
      </div>
    </div>

    <!-- Filtre -->
    <div class="flex gap-3">
      <button @click="filterUnread = false" class="px-4 py-1.5 text-sm rounded-full transition font-medium"
        :class="!filterUnread ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
        Toutes
      </button>
      <button @click="filterUnread = true" class="px-4 py-1.5 text-sm rounded-full transition font-medium flex items-center gap-1.5"
        :class="filterUnread ? 'bg-brand-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
        Non lues
        <span v-if="notifStore.unreadCount > 0" class="bg-red-500 text-white text-[10px] rounded-full px-1.5 py-0.5 font-bold">{{ notifStore.unreadCount }}</span>
      </button>
    </div>

    <!-- Liste -->
    <div class="card divide-y divide-gray-100">
      <div v-if="loading" class="flex justify-center py-16 text-gray-400">
        <svg class="animate-spin w-6 h-6" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
      </div>
      <p v-else-if="!filtered.length" class="px-5 py-12 text-center text-gray-400 text-sm">Aucune notification</p>
      <div v-else v-for="n in filtered" :key="n.id"
           class="flex items-start gap-4 px-5 py-4 hover:bg-gray-50 transition-colors"
           :class="{ 'bg-blue-50/40': !n.read_at }">
        <!-- Dot -->
        <div class="mt-1 flex-shrink-0">
          <span class="w-2.5 h-2.5 rounded-full block" :class="dotColor(n.data?.type)"></span>
        </div>
        <!-- Content -->
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-sm font-semibold text-gray-800">{{ n.data?.title }}</p>
              <p class="text-sm text-gray-600 mt-0.5">{{ n.data?.body }}</p>
              <div class="flex items-center gap-3 mt-1.5">
                <span class="text-xs text-gray-400">{{ formatDate(n.created_at) }}</span>
                <span v-if="n.data?.sent_by_name" class="text-xs text-gray-400">· De : {{ n.data.sent_by_name }}</span>
                <span v-if="!n.read_at" class="text-xs font-medium text-brand-600">Non lue</span>
              </div>
            </div>
            <div class="flex items-center gap-1 flex-shrink-0">
              <button v-if="!n.read_at" @click="markOne(n)" title="Marquer comme lue" class="p-1.5 rounded hover:bg-gray-200 text-gray-400 hover:text-green-600 transition">
                <Icon name="check" class="w-4 h-4" />
              </button>
              <button v-if="isAdmin" @click="del(n)" class="p-1.5 rounded hover:bg-gray-200 text-gray-400 hover:text-red-600 transition">
                <Icon name="trash-2" class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <Pagination :meta="meta" @change="loadPage" />

    <!-- Modal envoyer -->
    <Modal v-model="showSend" title="Envoyer une notification" size="md">
      <form @submit.prevent="send" class="space-y-4">
        <Field label="Titre *">
          <input v-model="sendForm.title" class="field" required />
        </Field>
        <Field label="Message *">
          <textarea v-model="sendForm.body" rows="4" class="field" required />
        </Field>
        <div class="grid grid-cols-2 gap-4">
          <Field label="Type">
            <select v-model="sendForm.type" class="field">
              <option value="info">Info</option>
              <option value="success">Succès</option>
              <option value="warning">Avertissement</option>
              <option value="alert">Alerte</option>
            </select>
          </Field>
          <Field label="Destinataire">
            <select v-model="sendForm.user_id" class="field">
              <option value="">Tous les utilisateurs</option>
              <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }}</option>
            </select>
          </Field>
        </div>
        <div class="flex justify-end gap-3 pt-2">
          <button type="button" @click="showSend=false" class="btn-secondary">Annuler</button>
          <button type="submit" :disabled="sending" class="btn-primary">{{ sending ? '...' : 'Envoyer' }}</button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useNotificationStore } from '../stores/notifications'
import api from '../api/client'
import Modal from '../components/ui/Modal.vue'
import Field from '../components/ui/Field.vue'
import Icon from '../components/ui/Icon.vue'
import Pagination from '../components/ui/Pagination.vue'

const auth       = useAuthStore()
const notifStore = useNotificationStore()
const isAdmin    = computed(() => auth.user?.role !== 'agent')

const allNotifs   = ref([])
const meta        = ref(null)
const loading     = ref(false)
const filterUnread = ref(false)
const showSend    = ref(false)
const sending     = ref(false)
const users       = ref([])

const filtered = computed(() =>
  filterUnread.value ? allNotifs.value.filter(n => !n.read_at) : allNotifs.value
)

const sendForm = reactive({ title: '', body: '', type: 'info', user_id: '' })

function dotColor(type) {
  return { info: 'bg-blue-500', success: 'bg-green-500', warning: 'bg-orange-500', alert: 'bg-red-500' }[type] ?? 'bg-gray-400'
}

function formatDate(d) {
  if (!d) return ''
  return new Date(d).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

async function loadPage(page = 1) {
  loading.value = true
  try {
    const { data } = await api.get(`/notifications?page=${page}&per_page=20`)
    allNotifs.value = data.data
    meta.value      = data.meta
    notifStore.unreadCount = data.meta.unread_count ?? notifStore.unreadCount
  } finally { loading.value = false }
}

async function markOne(n) {
  await notifStore.markRead(n.id)
  n.read_at = new Date().toISOString()
}

async function markAll() {
  await notifStore.markAllRead()
  allNotifs.value.forEach(n => { n.read_at = n.read_at || new Date().toISOString() })
}

async function del(n) {
  try {
    await api.delete(`/notifications/${n.id}`)
    allNotifs.value = allNotifs.value.filter(x => x.id !== n.id)
  } catch {}
}

async function send() {
  sending.value = true
  try {
    await api.post('/notifications', {
      title:   sendForm.title,
      body:    sendForm.body,
      type:    sendForm.type,
      user_id: sendForm.user_id || undefined,
    })
    showSend.value = false
    Object.assign(sendForm, { title: '', body: '', type: 'info', user_id: '' })
    loadPage(1)
  } finally { sending.value = false }
}

onMounted(async () => {
  loadPage()
  if (isAdmin.value) {
    const { data } = await api.get('/users?per_page=200')
    users.value = data.data
  }
})
</script>
