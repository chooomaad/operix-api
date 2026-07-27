<template>
  <div class="flex h-screen font-sans overflow-hidden transition-colors duration-200"
       :class="theme.dark ? 'bg-surface-900' : 'bg-slate-100'">

    <!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
    <aside class="w-60 flex-shrink-0 flex flex-col shadow-xl"
           style="background: linear-gradient(160deg, #0d2137 0%, #0a1929 100%)">

      <!-- Logo -->
      <div class="px-4 py-4" style="border-bottom:1px solid rgba(255,255,255,.07)">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl flex items-center justify-center font-black text-white text-lg shadow-lg flex-shrink-0"
               style="background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%)">O</div>
          <div class="min-w-0">
            <p class="text-white font-bold text-sm leading-tight tracking-tight">Operix</p>
            <p class="text-xs leading-tight truncate" style="color:#5b8dab">{{ auth.user?.tenant?.name }}</p>
          </div>
        </div>
      </div>

      <!-- Nav -->
      <nav class="flex-1 overflow-y-auto py-3 px-2.5 space-y-0.5">
        <template v-if="isAgent">
          <SideSection label="Menu principal" />
          <NavItem to="/dashboard"     icon="layout-dashboard" :label="t('nav.dashboard')" />
          <NavItem to="/employees"     icon="users"            :label="t('nav.employees')" />
          <SideSection label="Compte" />
          <NavItem to="/notifications" icon="bell"             label="Notifications" :badge="notifStore.unreadCount" />
        </template>

        <template v-else>
          <SideSection label="Menu principal" />
          <NavItem to="/dashboard"     icon="layout-dashboard" :label="t('nav.dashboard')" />

          <SideSection label="Ressources humaines" />
          <NavItem to="/employees"     icon="users"           :label="t('nav.employees')" />
          <NavItem to="/users"         icon="user-cog"        label="Utilisateurs" />

          <SideSection label="HSSE" />
          <NavItem to="/incidents"     icon="triangle-alert"  :label="t('nav.incidents')" />
          <NavItem to="/near-miss"     icon="circle-alert"    :label="t('nav.near_miss')" />
          <NavItem to="/environment"   icon="leaf"            :label="t('nav.environment')" />
          <NavItem to="/gemba-walks"   icon="clipboard-list"  :label="t('nav.gemba_walks')" />
          <NavItem to="/breaches"      icon="shield-off"      :label="t('nav.breaches')" />

          <SideSection label="Opérations" />
          <NavItem to="/visitors"      icon="badge-check"     :label="t('nav.visitors')" />
          <NavItem to="/contractors"   icon="hard-hat"        :label="t('nav.contractors')" />
          <NavItem to="/equipment"     icon="wrench"          :label="t('nav.equipment')" />

          <SideSection label="Administration" />
          <NavItem to="/notifications" icon="bell"            label="Notifications" :badge="notifStore.unreadCount" />
        </template>
      </nav>

      <!-- User block -->
      <div class="px-3 py-3" style="border-top:1px solid rgba(255,255,255,.07)">
        <div class="flex items-center gap-2.5 px-2 py-2 rounded-xl" style="background:rgba(255,255,255,.05)">
          <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold flex-shrink-0"
               style="background:linear-gradient(135deg,#2563eb,#7c3aed)">
            {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
          </div>
          <div class="flex-1 min-w-0">
            <p class="text-white text-xs font-semibold truncate leading-tight">{{ auth.user?.name }}</p>
            <p class="text-[10px] capitalize leading-tight" style="color:#5b8dab">{{ auth.user?.role }}</p>
          </div>
          <span class="w-2 h-2 rounded-full bg-emerald-400 flex-shrink-0 shadow-sm shadow-emerald-400/50"></span>
        </div>
        <button @click="doLogout"
                class="mt-2 w-full flex items-center gap-2 text-xs px-3 py-1.5 rounded-lg transition-all hover:bg-white/10"
                style="color:#5b8dab">
          <Icon name="log-out" class="w-3.5 h-3.5" />
          {{ t('nav.logout') }}
        </button>
      </div>
    </aside>

    <!-- ── Main ─────────────────────────────────────────────────────────────── -->
    <div class="flex-1 flex flex-col overflow-hidden">

      <!-- Topbar -->
      <header class="h-14 flex items-center justify-between px-5 flex-shrink-0 transition-colors"
              :class="theme.dark
                ? 'bg-surface-800 border-b border-surface-700'
                : 'bg-white border-b border-gray-200/80 shadow-sm'">
        <div class="flex items-center gap-3">
          <div>
            <h1 class="text-sm font-bold leading-tight transition-colors"
                :class="theme.dark ? 'text-white' : 'text-gray-800'">
              {{ pageTitle }}
            </h1>
            <p class="text-[11px] leading-tight transition-colors"
               :class="theme.dark ? 'text-surface-400' : 'text-gray-400'">
              {{ auth.user?.tenant?.name }} — {{ today }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <!-- Lang toggle -->
          <button @click="toggleLang"
                  class="px-2.5 py-1.5 text-xs font-semibold rounded-lg border transition-all"
                  :class="theme.dark
                    ? 'border-surface-600 text-surface-400 hover:text-white hover:border-surface-500'
                    : 'border-gray-200 text-gray-500 hover:text-gray-800 hover:border-gray-300'">
            {{ locale === 'fr' ? 'EN' : 'FR' }}
          </button>

          <!-- Dark mode toggle -->
          <button @click="theme.toggle()"
                  class="w-9 h-9 flex items-center justify-center rounded-lg border transition-all"
                  :class="theme.dark
                    ? 'bg-surface-700 border-surface-600 text-amber-400 hover:bg-surface-600'
                    : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-gray-100 hover:text-gray-700'"
                  :title="theme.dark ? 'Mode clair' : 'Mode sombre'">
            <svg v-if="theme.dark" class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2.25a.75.75 0 01.75.75v2.25a.75.75 0 01-1.5 0V3a.75.75 0 01.75-.75zM7.5 12a4.5 4.5 0 119 0 4.5 4.5 0 01-9 0zM18.894 6.166a.75.75 0 00-1.06-1.06l-1.591 1.59a.75.75 0 101.06 1.061l1.591-1.59zM21.75 12a.75.75 0 01-.75.75h-2.25a.75.75 0 010-1.5H21a.75.75 0 01.75.75zM17.834 18.894a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 10-1.061 1.06l1.59 1.591zM12 18a.75.75 0 01.75.75V21a.75.75 0 01-1.5 0v-2.25A.75.75 0 0112 18zM7.758 17.303a.75.75 0 00-1.061-1.06l-1.591 1.59a.75.75 0 001.06 1.061l1.591-1.59zM6 12a.75.75 0 01-.75.75H3a.75.75 0 010-1.5h2.25A.75.75 0 016 12zM6.697 7.757a.75.75 0 001.06-1.06l-1.59-1.591a.75.75 0 00-1.061 1.06l1.59 1.591z"/>
            </svg>
            <svg v-else class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
              <path fill-rule="evenodd" d="M9.528 1.718a.75.75 0 01.162.819A8.97 8.97 0 009 6a9 9 0 009 9 8.97 8.97 0 003.463-.69.75.75 0 01.981.98 10.503 10.503 0 01-9.694 6.46c-5.799 0-10.5-4.701-10.5-10.5 0-4.368 2.667-8.112 6.46-9.694a.75.75 0 01.818.162z" clip-rule="evenodd"/>
            </svg>
          </button>

          <NotificationBell />

          <!-- Avatar -->
          <div class="w-9 h-9 rounded-xl flex items-center justify-center text-white text-sm font-bold shadow-sm flex-shrink-0"
               style="background:linear-gradient(135deg,#2563eb,#7c3aed)">
            {{ auth.user?.name?.charAt(0)?.toUpperCase() }}
          </div>
        </div>
      </header>

      <!-- Content -->
      <main class="flex-1 overflow-y-auto p-5 transition-colors">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { setLocale } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { useThemeStore } from '../stores/theme'
import { useNotificationStore } from '../stores/notifications'
import NotificationBell from '../components/ui/NotificationBell.vue'
import Icon from '../components/ui/Icon.vue'
import NavItem from '../components/ui/NavItem.vue'
import SideSection from '../components/ui/SideSection.vue'

const auth       = useAuthStore()
const theme      = useThemeStore()
const notifStore = useNotificationStore()
const router     = useRouter()
const route      = useRoute()
const { t, locale } = useI18n()

const isAgent = computed(() => auth.user?.role === 'agent')

const today = new Date().toLocaleDateString('fr-FR', {
  weekday: 'long', day: 'numeric', month: 'long', year: 'numeric',
})

const pageTitles = {
  '/dashboard':     'Tableau de bord',
  '/employees':     'Employés',
  '/users':         'Utilisateurs',
  '/incidents':     'Incidents',
  '/near-miss':     "Presqu'accidents",
  '/environment':   'Environnement',
  '/gemba-walks':   'Gemba Walks',
  '/breaches':      'Infractions',
  '/visitors':      'Visiteurs',
  '/contractors':   'Sous-traitants',
  '/equipment':     'Équipements',
  '/notifications': 'Notifications',
}
const pageTitle = computed(() => {
  if (route.path.startsWith('/employees/') && route.params.id) return 'Profil employé'
  return pageTitles[route.path] ?? 'Operix'
})

function toggleLang() {
  setLocale(locale.value === 'fr' ? 'en' : 'fr')
}

async function doLogout() {
  await auth.logout()
  router.push('/login')
}

onMounted(() => {
  notifStore.fetchRecent()
})
</script>
