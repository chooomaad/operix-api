<template>
  <div class="space-y-5">

    <!-- Back + Header -->
    <div class="flex items-center gap-3">
      <button @click="router.back()" class="p-1.5 rounded-lg hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition">
        <Icon name="arrow-left" class="w-5 h-5" />
      </button>
      <div class="flex-1 min-w-0">
        <h2 v-if="employee" class="text-xl font-bold text-gray-900">{{ employee.prenom }} {{ employee.nom }}</h2>
        <div v-else class="h-6 w-48 bg-gray-200 animate-pulse rounded" />
        <p v-if="employee" class="text-sm text-gray-500 mt-0.5">{{ employee.poste }} · Matricule {{ employee.matricule }}</p>
      </div>
      <span v-if="employee" class="px-3 py-1 text-xs font-medium rounded-full"
        :class="employee.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
        {{ employee.is_active ? 'Actif' : 'Inactif' }}
      </span>
    </div>

    <!-- Loader global -->
    <div v-if="loading" class="flex justify-center py-24 text-gray-300">
      <svg class="animate-spin w-8 h-8" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
      </svg>
    </div>

    <template v-else-if="employee">
      <!-- Onglets -->
      <div class="border-b border-gray-200">
        <nav class="flex gap-1 -mb-px">
          <button v-for="tab in tabs" :key="tab.key" @click="activeTab = tab.key"
            class="px-4 py-2.5 text-sm font-medium border-b-2 transition whitespace-nowrap"
            :class="activeTab === tab.key
              ? 'border-blue-600 text-blue-600'
              : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
            {{ tab.label }}
            <span v-if="tab.count !== undefined" class="ml-1.5 px-1.5 py-0.5 text-xs rounded-full bg-gray-100 text-gray-600">{{ tab.count }}</span>
          </button>
        </nav>
      </div>

      <!-- ─── Onglet Infos ─────────────────────────────── -->
      <div v-if="activeTab === 'infos'" class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
          <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Informations personnelles</h3>
          <InfoRow label="Prénom" :value="employee.prenom" />
          <InfoRow label="Nom" :value="employee.nom" />
          <InfoRow label="Genre" :value="employee.gender === 'M' ? 'Masculin' : employee.gender === 'F' ? 'Féminin' : '—'" />
          <InfoRow label="Date de naissance" :value="fmtDate(employee.date_naissance)" />
          <InfoRow label="Lieu de naissance" :value="employee.lieu_naissance" />
          <InfoRow label="Nationalité" :value="employee.nationalite" />
          <InfoRow label="N° CNI" :value="employee.num_cni" />
          <InfoRow label="Adresse" :value="employee.adresse" />
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4">
          <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Informations professionnelles</h3>
          <InfoRow label="Matricule" :value="employee.matricule" />
          <InfoRow label="Poste" :value="employee.poste" />
          <InfoRow label="Département" :value="employee.department?.name" />
          <InfoRow label="Type de contrat" :value="employee.type_contrat" />
          <InfoRow label="Date d'embauche" :value="fmtDate(employee.date_embauche)" />
          <InfoRow label="Fin de contrat" :value="fmtDate(employee.date_fin_contrat)" />
          <InfoRow label="Email" :value="employee.email" />
          <InfoRow label="Téléphone" :value="employee.phone" />
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-5 space-y-4 md:col-span-2">
          <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Contact d'urgence</h3>
          <div class="grid grid-cols-2 gap-4">
            <InfoRow label="Nom" :value="employee.contact_urgence_nom" />
            <InfoRow label="Téléphone" :value="employee.contact_urgence_tel" />
          </div>
        </div>
      </div>

      <!-- ─── Onglet Formations ────────────────────────── -->
      <div v-if="activeTab === 'formations'">
        <div class="flex justify-between items-center mb-4">
          <p class="text-sm text-gray-500">{{ formations.length }} formation(s) enregistrée(s)</p>
          <button v-if="isAdmin" @click="openFormationCreate" class="flex items-center gap-2 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
            <Icon name="plus" class="w-4 h-4" /> Ajouter
          </button>
        </div>
        <div v-if="loadingFormations" class="py-10 text-center text-gray-300"><svg class="animate-spin w-5 h-5 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg></div>
        <div v-else-if="!formations.length" class="text-center py-12 text-gray-400">
          <Icon name="book-open" class="w-10 h-10 mx-auto mb-2 opacity-30" />
          <p>Aucune formation enregistrée</p>
        </div>
        <div v-else class="space-y-3">
          <div v-for="f in formations" :key="f.id" class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-4">
            <div class="rounded-lg p-2 bg-blue-50 flex-shrink-0"><Icon name="graduation-cap" class="w-5 h-5 text-blue-500" /></div>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-medium text-gray-900">{{ f.titre }}</p>
                  <p class="text-sm text-gray-500 mt-0.5">{{ f.organisme || '—' }}</p>
                </div>
                <span class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded-full" :class="statutClass(f.statut)">{{ f.statut }}</span>
              </div>
              <div class="mt-2 flex flex-wrap gap-4 text-xs text-gray-500">
                <span><span class="font-medium">Début :</span> {{ fmtDate(f.date_debut) }}</span>
                <span v-if="f.date_fin"><span class="font-medium">Fin :</span> {{ fmtDate(f.date_fin) }}</span>
                <span v-if="f.duree_jours"><span class="font-medium">Durée :</span> {{ f.duree_jours }} j</span>
                <span><span class="font-medium">Type :</span> {{ f.type }}</span>
              </div>
              <p v-if="f.observations" class="mt-1.5 text-xs text-gray-400 italic">{{ f.observations }}</p>
            </div>
            <div v-if="isAdmin" class="flex gap-1 flex-shrink-0">
              <button @click="openFormationEdit(f)" class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-blue-600 transition"><Icon name="edit" class="w-4 h-4" /></button>
              <button @click="deleteFormation(f)" class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-red-600 transition"><Icon name="trash-2" class="w-4 h-4" /></button>
            </div>
          </div>
        </div>
      </div>

      <!-- ─── Onglet Certifications ─────────────────────── -->
      <div v-if="activeTab === 'certifications'">
        <div class="flex justify-between items-center mb-4">
          <p class="text-sm text-gray-500">{{ certifications.length }} certification(s)</p>
          <button v-if="isAdmin" @click="openCertCreate" class="flex items-center gap-2 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
            <Icon name="plus" class="w-4 h-4" /> Ajouter
          </button>
        </div>
        <div v-if="loadingCerts" class="py-10 text-center text-gray-300"><svg class="animate-spin w-5 h-5 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg></div>
        <div v-else-if="!certifications.length" class="text-center py-12 text-gray-400">
          <Icon name="award" class="w-10 h-10 mx-auto mb-2 opacity-30" />
          <p>Aucune certification enregistrée</p>
        </div>
        <div v-else class="space-y-3">
          <div v-for="c in certifications" :key="c.id" class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-4">
            <div class="rounded-lg p-2 flex-shrink-0" :class="c.is_expired ? 'bg-red-50' : 'bg-green-50'">
              <Icon name="award" class="w-5 h-5" :class="c.is_expired ? 'text-red-400' : 'text-green-500'" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-medium text-gray-900">{{ c.titre }}</p>
                  <p class="text-sm text-gray-500 mt-0.5">{{ c.organisme || '—' }}<span v-if="c.numero"> · N° {{ c.numero }}</span></p>
                </div>
                <span class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded-full" :class="c.is_expired ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-700'">
                  {{ c.is_expired ? 'Expirée' : 'Valide' }}
                </span>
              </div>
              <div class="mt-2 flex flex-wrap gap-4 text-xs text-gray-500">
                <span><span class="font-medium">Obtention :</span> {{ fmtDate(c.date_obtention) }}</span>
                <span v-if="c.date_expiration"><span class="font-medium">Expiration :</span> {{ fmtDate(c.date_expiration) }}</span>
              </div>
            </div>
            <div v-if="isAdmin" class="flex gap-1 flex-shrink-0">
              <button @click="openCertEdit(c)" class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-blue-600 transition"><Icon name="edit" class="w-4 h-4" /></button>
              <button @click="deleteCert(c)" class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-red-600 transition"><Icon name="trash-2" class="w-4 h-4" /></button>
            </div>
          </div>
        </div>
      </div>

      <!-- ─── Onglet Visites médicales ─────────────────── -->
      <div v-if="activeTab === 'visites'">
        <div class="flex justify-between items-center mb-4">
          <p class="text-sm text-gray-500">{{ visits.length }} visite(s) médicale(s)</p>
          <button v-if="isAdmin" @click="openVisitCreate" class="flex items-center gap-2 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
            <Icon name="plus" class="w-4 h-4" /> Ajouter
          </button>
        </div>
        <div v-if="loadingVisits" class="py-10 text-center text-gray-300"><svg class="animate-spin w-5 h-5 inline" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg></div>
        <div v-else-if="!visits.length" class="text-center py-12 text-gray-400">
          <Icon name="stethoscope" class="w-10 h-10 mx-auto mb-2 opacity-30" />
          <p>Aucune visite médicale enregistrée</p>
        </div>
        <div v-else class="space-y-3">
          <div v-for="v in visits" :key="v.id" class="bg-white rounded-xl border border-gray-200 p-4 flex items-start gap-4">
            <div class="rounded-lg p-2 flex-shrink-0" :class="resultatBg(v.resultat)">
              <Icon name="heart-pulse" class="w-5 h-5" :class="resultatColor(v.resultat)" />
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-start justify-between gap-2">
                <div>
                  <p class="font-medium text-gray-900 capitalize">{{ v.type }}</p>
                  <p class="text-sm text-gray-500 mt-0.5">Dr. {{ v.medecin || '—' }}</p>
                </div>
                <span class="flex-shrink-0 px-2 py-0.5 text-xs font-medium rounded-full" :class="resultatBadge(v.resultat)">
                  {{ resultatLabel(v.resultat) }}
                </span>
              </div>
              <div class="mt-2 flex flex-wrap gap-4 text-xs text-gray-500">
                <span><span class="font-medium">Date :</span> {{ fmtDate(v.date) }}</span>
                <span v-if="v.prochaine_visite"><span class="font-medium">Prochaine :</span> {{ fmtDate(v.prochaine_visite) }}</span>
              </div>
              <p v-if="v.restrictions" class="mt-1.5 text-xs text-orange-600">Restrictions : {{ v.restrictions }}</p>
              <p v-if="v.observations" class="mt-1 text-xs text-gray-400 italic">{{ v.observations }}</p>
            </div>
            <div v-if="isAdmin" class="flex gap-1 flex-shrink-0">
              <button @click="openVisitEdit(v)" class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-blue-600 transition"><Icon name="edit" class="w-4 h-4" /></button>
              <button @click="deleteVisit(v)" class="p-1 rounded hover:bg-gray-100 text-gray-400 hover:text-red-600 transition"><Icon name="trash-2" class="w-4 h-4" /></button>
            </div>
          </div>
        </div>
      </div>

      <!-- ─── Onglet Incidents ──────────────────────────── -->
      <div v-if="activeTab === 'incidents'">
        <div v-if="!employee.breaches?.length" class="text-center py-12 text-gray-400">
          <Icon name="shield-check" class="w-10 h-10 mx-auto mb-2 opacity-30" />
          <p>Aucun incident enregistré</p>
        </div>
        <div v-else class="space-y-3">
          <div v-for="b in employee.breaches" :key="b.id" class="bg-white rounded-xl border border-gray-200 p-4">
            <div class="flex items-start justify-between">
              <div>
                <p class="font-medium text-gray-900">{{ b.titre }}</p>
                <p class="text-sm text-gray-500 mt-0.5">{{ fmtDate(b.date) }}</p>
              </div>
              <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-600">{{ b.type }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>

    <!-- ─── Modal Formation ─────────────────────────────── -->
    <Modal v-model="showFormationModal" :title="editingFormation ? 'Modifier la formation' : 'Nouvelle formation'" size="md">
      <form @submit.prevent="saveFormation" class="space-y-4">
        <Field label="Titre *"><input v-model="formationForm.titre" class="field" required /></Field>
        <Field label="Organisme"><input v-model="formationForm.organisme" class="field" /></Field>
        <div class="grid grid-cols-2 gap-4">
          <Field label="Date début *"><input v-model="formationForm.date_debut" type="date" class="field" required /></Field>
          <Field label="Date fin"><input v-model="formationForm.date_fin" type="date" class="field" /></Field>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <Field label="Durée (jours)"><input v-model="formationForm.duree_jours" type="number" min="1" class="field" /></Field>
          <Field label="Type">
            <select v-model="formationForm.type" class="field">
              <option value="">—</option>
              <option value="interne">Interne</option>
              <option value="externe">Externe</option>
              <option value="elearning">E-learning</option>
              <option value="autre">Autre</option>
            </select>
          </Field>
        </div>
        <Field label="Statut">
          <select v-model="formationForm.statut" class="field">
            <option value="planifiee">Planifiée</option>
            <option value="en_cours">En cours</option>
            <option value="terminee">Terminée</option>
            <option value="annulee">Annulée</option>
          </select>
        </Field>
        <Field label="Observations"><textarea v-model="formationForm.observations" class="field" rows="2" /></Field>
        <div class="flex justify-end gap-3 pt-1">
          <button type="button" @click="showFormationModal = false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">Annuler</button>
          <button type="submit" :disabled="savingFormation" class="px-4 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium disabled:opacity-60">{{ savingFormation ? '...' : 'Enregistrer' }}</button>
        </div>
      </form>
    </Modal>

    <!-- ─── Modal Certification ──────────────────────────── -->
    <Modal v-model="showCertModal" :title="editingCert ? 'Modifier la certification' : 'Nouvelle certification'" size="md">
      <form @submit.prevent="saveCert" class="space-y-4">
        <Field label="Titre *"><input v-model="certForm.titre" class="field" required /></Field>
        <Field label="Organisme"><input v-model="certForm.organisme" class="field" /></Field>
        <Field label="N° de certificat"><input v-model="certForm.numero" class="field" /></Field>
        <div class="grid grid-cols-2 gap-4">
          <Field label="Date d'obtention *"><input v-model="certForm.date_obtention" type="date" class="field" required /></Field>
          <Field label="Date d'expiration"><input v-model="certForm.date_expiration" type="date" class="field" /></Field>
        </div>
        <div class="flex justify-end gap-3 pt-1">
          <button type="button" @click="showCertModal = false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">Annuler</button>
          <button type="submit" :disabled="savingCert" class="px-4 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium disabled:opacity-60">{{ savingCert ? '...' : 'Enregistrer' }}</button>
        </div>
      </form>
    </Modal>

    <!-- ─── Modal Visite médicale ────────────────────────── -->
    <Modal v-model="showVisitModal" :title="editingVisit ? 'Modifier la visite' : 'Nouvelle visite médicale'" size="md">
      <form @submit.prevent="saveVisit" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <Field label="Date *"><input v-model="visitForm.date" type="date" class="field" required /></Field>
          <Field label="Type">
            <select v-model="visitForm.type" class="field">
              <option value="embauche">Embauche</option>
              <option value="periodique">Périodique</option>
              <option value="reprise">Reprise</option>
              <option value="spontanee">Spontanée</option>
            </select>
          </Field>
        </div>
        <Field label="Résultat">
          <select v-model="visitForm.resultat" class="field">
            <option value="apte">Apte</option>
            <option value="apte_restrictions">Apte avec restrictions</option>
            <option value="inapte">Inapte</option>
          </select>
        </Field>
        <Field label="Médecin"><input v-model="visitForm.medecin" class="field" /></Field>
        <Field label="Prochaine visite"><input v-model="visitForm.prochaine_visite" type="date" class="field" /></Field>
        <Field label="Restrictions"><input v-model="visitForm.restrictions" class="field" /></Field>
        <Field label="Observations"><textarea v-model="visitForm.observations" class="field" rows="2" /></Field>
        <div class="flex justify-end gap-3 pt-1">
          <button type="button" @click="showVisitModal = false" class="px-4 py-2 text-sm rounded-lg border border-gray-300 hover:bg-gray-50">Annuler</button>
          <button type="submit" :disabled="savingVisit" class="px-4 py-2 text-sm rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-medium disabled:opacity-60">{{ savingVisit ? '...' : 'Enregistrer' }}</button>
        </div>
      </form>
    </Modal>

  </div>
</template>

<script setup>
import { ref, computed, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useFetch } from '../../composables/useFetch'
import Icon from '../../components/ui/Icon.vue'
import Modal from '../../components/ui/Modal.vue'
import Field from '../../components/ui/Field.vue'

// ── Helpers de sous-composant inline ──────────────────────────────────────────
const InfoRow = {
  props: ['label', 'value'],
  template: `<div class="flex justify-between text-sm gap-4">
    <span class="text-gray-500 flex-shrink-0">{{ label }}</span>
    <span class="text-gray-900 font-medium text-right">{{ value || '—' }}</span>
  </div>`,
}

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()
const { get, post, put, del } = useFetch()

const isAdmin = computed(() => auth.user?.role !== 'agent')
const empId   = computed(() => route.params.id)

// ── Données ────────────────────────────────────────────────────────────────────
const loading      = ref(true)
const employee     = ref(null)
const formations   = ref([])
const certifications = ref([])
const visits       = ref([])

const loadingFormations = ref(false)
const loadingCerts      = ref(false)
const loadingVisits     = ref(false)

const activeTab = ref('infos')
const tabs = computed(() => [
  { key: 'infos',          label: 'Informations' },
  { key: 'formations',     label: 'Formations',     count: formations.value.length },
  { key: 'certifications', label: 'Certifications', count: certifications.value.length },
  { key: 'visites',        label: 'Visites médicales', count: visits.value.length },
  { key: 'incidents',      label: 'Incidents',      count: employee.value?.breaches?.length ?? 0 },
])

// ── Chargement initial ─────────────────────────────────────────────────────────
onMounted(async () => {
  try {
    const data = await get(`/employees/${empId.value}`)
    employee.value = data
  } finally {
    loading.value = false
  }
  loadSubData()
})

async function loadSubData() {
  loadingFormations.value = true
  loadingCerts.value = true
  loadingVisits.value = true
  const [f, c, v] = await Promise.all([
    get(`/employees/${empId.value}/formations`).catch(() => []),
    get(`/employees/${empId.value}/certifications`).catch(() => []),
    get(`/employees/${empId.value}/medical-visits`).catch(() => []),
  ])
  formations.value     = f
  certifications.value = c
  visits.value         = v
  loadingFormations.value = false
  loadingCerts.value      = false
  loadingVisits.value     = false
}

// ── Utilitaires ────────────────────────────────────────────────────────────────
function fmtDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' })
}

function statutClass(s) {
  const m = { planifiee: 'bg-blue-100 text-blue-700', en_cours: 'bg-orange-100 text-orange-700', terminee: 'bg-green-100 text-green-700', annulee: 'bg-gray-100 text-gray-500' }
  return m[s] ?? 'bg-gray-100 text-gray-500'
}

function resultatBg(r)    { return { apte: 'bg-green-50', apte_restrictions: 'bg-orange-50', inapte: 'bg-red-50' }[r] ?? 'bg-gray-50' }
function resultatColor(r) { return { apte: 'text-green-500', apte_restrictions: 'text-orange-500', inapte: 'text-red-500' }[r] ?? 'text-gray-400' }
function resultatBadge(r) { return { apte: 'bg-green-100 text-green-700', apte_restrictions: 'bg-orange-100 text-orange-600', inapte: 'bg-red-100 text-red-600' }[r] ?? '' }
function resultatLabel(r) { return { apte: 'Apte', apte_restrictions: 'Apte (restrictions)', inapte: 'Inapte' }[r] ?? r }

// ── Formations CRUD ────────────────────────────────────────────────────────────
const showFormationModal = ref(false)
const editingFormation   = ref(null)
const savingFormation    = ref(false)
const formationForm      = reactive({ titre: '', organisme: '', date_debut: '', date_fin: '', duree_jours: '', type: '', statut: 'planifiee', observations: '' })

function openFormationCreate() {
  Object.assign(formationForm, { titre: '', organisme: '', date_debut: '', date_fin: '', duree_jours: '', type: '', statut: 'planifiee', observations: '' })
  editingFormation.value = null
  showFormationModal.value = true
}
function openFormationEdit(f) {
  Object.assign(formationForm, { titre: f.titre, organisme: f.organisme ?? '', date_debut: f.date_debut?.slice(0, 10) ?? '', date_fin: f.date_fin?.slice(0, 10) ?? '', duree_jours: f.duree_jours ?? '', type: f.type ?? '', statut: f.statut ?? 'planifiee', observations: f.observations ?? '' })
  editingFormation.value = f
  showFormationModal.value = true
}
async function saveFormation() {
  savingFormation.value = true
  try {
    if (editingFormation.value) {
      const updated = await put(`/employees/${empId.value}/formations/${editingFormation.value.id}`, formationForm)
      const idx = formations.value.findIndex(f => f.id === editingFormation.value.id)
      if (idx !== -1) formations.value[idx] = updated
    } else {
      const created = await post(`/employees/${empId.value}/formations`, formationForm)
      formations.value.unshift(created)
    }
    showFormationModal.value = false
  } finally {
    savingFormation.value = false
  }
}
async function deleteFormation(f) {
  if (!confirm('Supprimer cette formation ?')) return
  await del(`/employees/${empId.value}/formations/${f.id}`)
  formations.value = formations.value.filter(x => x.id !== f.id)
}

// ── Certifications CRUD ────────────────────────────────────────────────────────
const showCertModal = ref(false)
const editingCert   = ref(null)
const savingCert    = ref(false)
const certForm      = reactive({ titre: '', organisme: '', numero: '', date_obtention: '', date_expiration: '' })

function openCertCreate() {
  Object.assign(certForm, { titre: '', organisme: '', numero: '', date_obtention: '', date_expiration: '' })
  editingCert.value = null
  showCertModal.value = true
}
function openCertEdit(c) {
  Object.assign(certForm, { titre: c.titre, organisme: c.organisme ?? '', numero: c.numero ?? '', date_obtention: c.date_obtention?.slice(0, 10) ?? '', date_expiration: c.date_expiration?.slice(0, 10) ?? '' })
  editingCert.value = c
  showCertModal.value = true
}
async function saveCert() {
  savingCert.value = true
  try {
    if (editingCert.value) {
      const updated = await put(`/employees/${empId.value}/certifications/${editingCert.value.id}`, certForm)
      const idx = certifications.value.findIndex(c => c.id === editingCert.value.id)
      if (idx !== -1) certifications.value[idx] = updated
    } else {
      const created = await post(`/employees/${empId.value}/certifications`, certForm)
      certifications.value.unshift(created)
    }
    showCertModal.value = false
  } finally {
    savingCert.value = false
  }
}
async function deleteCert(c) {
  if (!confirm('Supprimer cette certification ?')) return
  await del(`/employees/${empId.value}/certifications/${c.id}`)
  certifications.value = certifications.value.filter(x => x.id !== c.id)
}

// ── Visites médicales CRUD ─────────────────────────────────────────────────────
const showVisitModal = ref(false)
const editingVisit   = ref(null)
const savingVisit    = ref(false)
const visitForm      = reactive({ date: '', type: 'periodique', resultat: 'apte', medecin: '', prochaine_visite: '', restrictions: '', observations: '' })

function openVisitCreate() {
  Object.assign(visitForm, { date: '', type: 'periodique', resultat: 'apte', medecin: '', prochaine_visite: '', restrictions: '', observations: '' })
  editingVisit.value = null
  showVisitModal.value = true
}
function openVisitEdit(v) {
  Object.assign(visitForm, { date: v.date?.slice(0, 10) ?? '', type: v.type ?? 'periodique', resultat: v.resultat ?? 'apte', medecin: v.medecin ?? '', prochaine_visite: v.prochaine_visite?.slice(0, 10) ?? '', restrictions: v.restrictions ?? '', observations: v.observations ?? '' })
  editingVisit.value = v
  showVisitModal.value = true
}
async function saveVisit() {
  savingVisit.value = true
  try {
    if (editingVisit.value) {
      const updated = await put(`/employees/${empId.value}/medical-visits/${editingVisit.value.id}`, visitForm)
      const idx = visits.value.findIndex(v => v.id === editingVisit.value.id)
      if (idx !== -1) visits.value[idx] = updated
    } else {
      const created = await post(`/employees/${empId.value}/medical-visits`, visitForm)
      visits.value.unshift(created)
    }
    showVisitModal.value = false
  } finally {
    savingVisit.value = false
  }
}
async function deleteVisit(v) {
  if (!confirm('Supprimer cette visite médicale ?')) return
  await del(`/employees/${empId.value}/medical-visits/${v.id}`)
  visits.value = visits.value.filter(x => x.id !== v.id)
}
</script>
