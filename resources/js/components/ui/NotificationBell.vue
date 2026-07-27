<template>
  <div class="relative" ref="bellRef">
    <button @click="toggle"
            class="relative w-9 h-9 flex items-center justify-center rounded-lg border transition-all"
            :class="dark
              ? 'bg-surface-700 border-surface-600 text-surface-300 hover:text-white hover:bg-surface-600'
              : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-gray-100 hover:text-gray-700'">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
      <span v-if="store.unreadCount > 0"
            class="absolute -top-1 -right-1 min-w-[17px] h-[17px] bg-red-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center px-0.5 shadow-sm shadow-red-500/50 animate-pulse">
        {{ store.unreadCount > 99 ? '99+' : store.unreadCount }}
      </span>
    </button>

    <Transition
      enter-active-class="transition ease-out duration-150"
      enter-from-class="opacity-0 translate-y-1 scale-95"
      enter-to-class="opacity-100 translate-y-0 scale-100"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0 translate-y-1 scale-95">
      <div v-if="open"
           class="absolute right-0 top-12 w-80 rounded-2xl shadow-2xl z-50 overflow-hidden"
           :class="dark
             ? 'bg-surface-800 border border-surface-700'
             : 'bg-white border border-gray-200'">
        <!-- Header -->
        <div class="flex items-center justify-between px-4 py-3"
             :class="dark ? 'border-b border-surface-700' : 'border-b border-gray-100'">
          <div class="flex items-center gap-2">
            <span class="font-semibold text-sm" :class="dark ? 'text-white' : 'text-gray-800'">Notifications</span>
            <span v-if="store.unreadCount > 0"
                  class="px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-red-500 text-white">
              {{ store.unreadCount }}
            </span>
          </div>
          <button @click="markAll"
                  class="text-xs font-medium text-brand-500 hover:text-brand-600 transition">
            Tout lire
          </button>
        </div>

        <!-- List -->
        <div class="max-h-72 overflow-y-auto">
          <p v-if="!store.recent.length"
             class="px-4 py-8 text-center text-sm"
             :class="dark ? 'text-surface-500' : 'text-gray-400'">
            Aucune notification
          </p>
          <div v-for="n in store.recent" :key="n.id" @click="read(n)"
               class="px-4 py-3 cursor-pointer transition-colors"
               :class="[
                 dark
                   ? (!n.read_at ? 'bg-brand-900/20 hover:bg-brand-900/30' : 'hover:bg-surface-750')
                   : (!n.read_at ? 'bg-blue-50/70 hover:bg-blue-50' : 'hover:bg-gray-50'),
                 'border-b',
                 dark ? 'border-surface-700/50' : 'border-gray-50'
               ]">
            <div class="flex items-start gap-2.5">
              <span class="mt-1.5 w-2 h-2 rounded-full flex-shrink-0" :class="dotColor(n.data?.type)"></span>
              <div class="flex-1 min-w-0">
                <p class="text-[13px] font-semibold truncate"
                   :class="dark ? 'text-surface-100' : 'text-gray-800'">{{ n.data?.title }}</p>
                <p class="text-xs mt-0.5 line-clamp-2"
                   :class="dark ? 'text-surface-400' : 'text-gray-500'">{{ n.data?.body }}</p>
                <p class="text-[10px] mt-1"
                   :class="dark ? 'text-surface-500' : 'text-gray-400'">{{ timeAgo(n.created_at) }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-4 py-2.5" :class="dark ? 'border-t border-surface-700' : 'border-t border-gray-100'">
          <router-link to="/notifications" @click="open = false"
                       class="text-xs font-medium text-brand-500 hover:text-brand-600 transition">
            Voir tout l'historique →
          </router-link>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import { useNotificationStore } from '../../stores/notifications'
import { useThemeStore } from '../../stores/theme'

const store  = useNotificationStore()
const { dark } = useThemeStore()
const open   = ref(false)
const bellRef = ref(null)
const router = useRouter()

function toggle() { open.value = !open.value }

function dotColor(type) {
  return { info: 'bg-blue-500', success: 'bg-emerald-500', warning: 'bg-amber-500', alert: 'bg-red-500' }[type] ?? 'bg-gray-400'
}

function timeAgo(dateStr) {
  if (!dateStr) return ''
  const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000)
  if (diff < 60)    return 'À l\'instant'
  if (diff < 3600)  return `${Math.floor(diff / 60)} min`
  if (diff < 86400) return `${Math.floor(diff / 3600)} h`
  return new Date(dateStr).toLocaleDateString('fr-FR')
}

async function read(n) {
  if (!n.read_at) await store.markRead(n.id)
  open.value = false
  router.push('/notifications')
}

async function markAll() { await store.markAllRead() }

function onClickOutside(e) {
  if (bellRef.value && !bellRef.value.contains(e.target)) open.value = false
}

onMounted(() => document.addEventListener('click', onClickOutside))
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>
