<template>
  <div class="space-y-6">

    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-24"
         :class="theme.dark ? 'text-surface-500' : 'text-gray-300'">
      <svg class="animate-spin w-7 h-7" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
      </svg>
    </div>

    <template v-else-if="kpis">

      <!-- ── Vue Agent ──────────────────────────────────────────── -->
      <template v-if="isAgent">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <KpiCard accent="#2563eb" icon="users"      label="Total employés"       :value="kpis.employeeKpis?.total ?? 0"   :sub="`Actifs : ${kpis.employeeKpis?.active ?? 0}`" />
          <KpiCard accent="#059669" icon="badge-check" label="Visiteurs sur site"  :value="kpis.visitorKpis?.on_site ?? 0"  sub="Présents actuellement" />
        </div>
        <div class="card p-6 text-center" :class="theme.dark ? 'text-surface-400' : 'text-gray-400'">
          <p class="text-sm">Accès limité — contactez un administrateur pour plus de statistiques.</p>
        </div>
      </template>

      <!-- ── Vue Admin ──────────────────────────────────────────── -->
      <template v-else>

        <!-- RH -->
        <section>
          <p class="text-[10px] font-bold uppercase tracking-widest mb-3 flex items-center gap-2"
             :class="theme.dark ? 'text-surface-500' : 'text-gray-400'">
            <span class="h-px flex-1" :class="theme.dark ? 'bg-surface-700' : 'bg-gray-200'"></span>
            Ressources humaines
            <span class="h-px flex-1" :class="theme.dark ? 'bg-surface-700' : 'bg-gray-200'"></span>
          </p>
          <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            <KpiCard accent="#2563eb" icon="users"      :label="t('dashboard.employees')" :value="kpis.employeeKpis?.total ?? 0"   :sub="`Actifs : ${kpis.employeeKpis?.active ?? 0}`" />
            <KpiCard accent="#7c3aed" icon="hard-hat"   :label="t('nav.contractors')"     :value="kpis.contractorKpis?.total ?? 0" :sub="`Actifs : ${kpis.contractorKpis?.active ?? 0}`" />
            <KpiCard accent="#0891b2" icon="badge-check" :label="t('dashboard.visitors')" :value="kpis.visitorKpis?.on_site ?? 0"  :sub="`Aujourd'hui : ${kpis.visitorKpis?.total_today ?? 0}`" />
            <KpiCard accent="#4f46e5" icon="wrench"      :label="t('dashboard.equipment')" :value="kpis.equipmentKpis?.total ?? 0" :sub="`À inspecter : ${kpis.equipmentKpis?.inspection_due ?? 0}`" />
          </div>
        </section>

        <!-- HSSE -->
        <section>
          <p class="text-[10px] font-bold uppercase tracking-widest mb-3 flex items-center gap-2"
             :class="theme.dark ? 'text-surface-500' : 'text-gray-400'">
            <span class="h-px flex-1" :class="theme.dark ? 'bg-surface-700' : 'bg-gray-200'"></span>
            HSSE
            <span class="h-px flex-1" :class="theme.dark ? 'bg-surface-700' : 'bg-gray-200'"></span>
          </p>
          <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
            <KpiCard accent="#dc2626" icon="triangle-alert" :label="t('nav.incidents')"   :value="kpis.safetyKpis?.total_incidents ?? 0" :sub="`Ouverts : ${kpis.safetyKpis?.open ?? 0}`" />
            <KpiCard accent="#d97706" icon="circle-alert"   :label="t('nav.near_miss')"   :value="kpis.safetyKpis?.total_near_miss ?? 0"  :sub="`Haut/Critique : ${kpis.safetyKpis?.high_critical ?? 0}`" />
            <KpiCard accent="#16a34a" icon="leaf"           :label="t('nav.environment')" :value="kpis.environmentKpis?.total ?? 0"        :sub="`Ouverts : ${kpis.environmentKpis?.open ?? 0}`" />
            <KpiCard accent="#9333ea" icon="clipboard-list" :label="t('nav.gemba_walks')" :value="kpis.gembaKpis?.total ?? 0"              :sub="`En retard : ${kpis.gembaKpis?.overdue ?? 0}`" />
          </div>
        </section>

        <!-- Stats safety -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

          <!-- TF -->
          <div class="lg:col-span-2 card p-6">
            <div class="flex items-start justify-between gap-4 mb-5">
              <div>
                <p class="text-[10px] font-bold uppercase tracking-widest"
                   :class="theme.dark ? 'text-surface-500' : 'text-gray-400'">{{ t('dashboard.taux_frequence') }}</p>
                <p class="text-4xl font-extrabold mt-1 tracking-tight"
                   :class="theme.dark ? 'text-white' : 'text-gray-900'">
                  {{ kpis.safetyKpis?.taux_frequence?.toFixed(2) ?? '—' }}
                </p>
                <p class="text-xs mt-1" :class="theme.dark ? 'text-surface-500' : 'text-gray-400'">
                  LTI × 1 000 000 / heures travaillées
                </p>
              </div>
              <div class="text-right">
                <p class="text-[10px] font-bold uppercase tracking-widest"
                   :class="theme.dark ? 'text-surface-500' : 'text-gray-400'">{{ t('dashboard.lti') }}</p>
                <p class="text-3xl font-extrabold mt-1 text-red-500">{{ kpis.safetyKpis?.lti_count ?? 0 }}</p>
              </div>
            </div>
            <div class="h-2.5 rounded-full overflow-hidden"
                 :class="theme.dark ? 'bg-surface-700' : 'bg-gray-100'">
              <div class="h-full rounded-full transition-all duration-700" :class="tfColor" :style="{ width: tfWidth }" />
            </div>
            <div class="flex justify-between text-[10px] mt-1"
                 :class="theme.dark ? 'text-surface-600' : 'text-gray-300'">
              <span>0</span><span>5</span><span>10</span><span>15</span><span>20+</span>
            </div>
          </div>

          <!-- Jours sans accident -->
          <div class="card p-6 flex flex-col items-center justify-center text-center">
            <p class="text-[10px] font-bold uppercase tracking-widest"
               :class="theme.dark ? 'text-surface-500' : 'text-gray-400'">Jours sans accident</p>
            <p class="text-6xl font-extrabold mt-3 tracking-tighter"
               :class="(kpis.safetyKpis?.days_without_accident ?? 0) > 0 ? 'text-emerald-500' : 'text-orange-500'">
              {{ kpis.safetyKpis?.days_without_accident ?? 0 }}
            </p>
            <p class="text-xs mt-2" :class="theme.dark ? 'text-surface-500' : 'text-gray-400'">jours consécutifs</p>
            <div class="mt-4 w-12 h-12 rounded-full flex items-center justify-center"
                 :class="(kpis.safetyKpis?.days_without_accident ?? 0) > 30 ? 'bg-emerald-500/10' : 'bg-orange-500/10'">
              <Icon name="shield-check" class="w-6 h-6"
                    :class="(kpis.safetyKpis?.days_without_accident ?? 0) > 30 ? 'text-emerald-500' : 'text-orange-500'" />
            </div>
          </div>
        </div>

      </template>
    </template>

    <!-- Error fallback -->
    <div v-else class="text-center py-20" :class="theme.dark ? 'text-surface-500' : 'text-gray-400'">
      <p>Impossible de charger les données.</p>
    </div>

  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAuthStore } from '../stores/auth'
import { useThemeStore } from '../stores/theme'
import api from '../api/client'
import KpiCard from '../components/ui/KpiCard.vue'
import Icon from '../components/ui/Icon.vue'

const { t }  = useI18n()
const auth   = useAuthStore()
const theme  = useThemeStore()
const kpis   = ref(null)
const loading = ref(true)

const isAgent = computed(() => auth.user?.role === 'agent')

const tfColor = computed(() => {
  const tf = kpis.value?.safetyKpis?.taux_frequence ?? 0
  if (tf === 0) return 'bg-emerald-500'
  if (tf < 5)   return 'bg-yellow-400'
  if (tf < 10)  return 'bg-orange-500'
  return 'bg-red-600'
})
const tfWidth = computed(() => {
  const tf = Math.min(kpis.value?.safetyKpis?.taux_frequence ?? 0, 20)
  return `${(tf / 20) * 100}%`
})

onMounted(async () => {
  try { const { data } = await api.get('/dashboard'); kpis.value = data }
  catch {} finally { loading.value = false }
})
</script>
