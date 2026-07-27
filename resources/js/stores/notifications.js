import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '../api/client'

export const useNotificationStore = defineStore('notifications', () => {
  const recent      = ref([])
  const unreadCount = ref(0)

  async function fetchRecent() {
    try {
      const { data } = await api.get('/notifications?per_page=8')
      recent.value      = data.data
      unreadCount.value = data.meta.unread_count ?? 0
    } catch {}
  }

  async function fetchUnreadCount() {
    try {
      const { data } = await api.get('/notifications/unread-count')
      unreadCount.value = data.count
    } catch {}
  }

  async function markRead(id) {
    try {
      await api.put(`/notifications/${id}/read`)
      const n = recent.value.find(x => x.id === id)
      if (n && !n.read_at) {
        n.read_at = new Date().toISOString()
        unreadCount.value = Math.max(0, unreadCount.value - 1)
      }
    } catch {}
  }

  async function markAllRead() {
    try {
      await api.post('/notifications/read-all')
      recent.value.forEach(n => { n.read_at = n.read_at || new Date().toISOString() })
      unreadCount.value = 0
    } catch {}
  }

  // Appelé par Echo en temps réel
  function addRealtime(payload) {
    recent.value.unshift({
      id:         payload.id,
      data:       { title: payload.title, body: payload.body, type: payload.type },
      read_at:    null,
      created_at: payload.created_at,
    })
    if (recent.value.length > 10) recent.value.pop()
    unreadCount.value++
  }

  return { recent, unreadCount, fetchRecent, fetchUnreadCount, markRead, markAllRead, addRealtime }
})
