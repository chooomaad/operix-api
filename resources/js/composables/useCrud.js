import { ref, reactive } from 'vue'
import api from '../api/client'

export function useCrud(endpoint) {
    const items      = ref([])
    const meta       = ref(null)
    const loading    = ref(false)
    const saving     = ref(false)
    const deleting   = ref(false)
    const errors     = ref({})
    const params     = reactive({ page: 1, per_page: 20, search: '', ...(arguments[1] || {}) })

    async function fetch(extra = {}) {
        loading.value = true
        try {
            const { data } = await api.get(endpoint, { params: { ...params, ...extra } })
            items.value = data.data
            meta.value  = data.meta
        } finally {
            loading.value = false
        }
    }

    async function create(payload) {
        saving.value  = true
        errors.value  = {}
        try {
            const { data } = await api.post(endpoint, payload)
            await fetch()
            return data
        } catch (e) {
            errors.value = e.response?.data?.errors || {}
            throw e
        } finally {
            saving.value = false
        }
    }

    async function update(id, payload) {
        saving.value  = true
        errors.value  = {}
        try {
            const { data } = await api.put(`${endpoint}/${id}`, payload)
            await fetch()
            return data
        } catch (e) {
            errors.value = e.response?.data?.errors || {}
            throw e
        } finally {
            saving.value = false
        }
    }

    async function remove(id) {
        deleting.value = true
        try {
            await api.delete(`${endpoint}/${id}`)
            await fetch()
        } finally {
            deleting.value = false
        }
    }

    async function action(path, payload = {}) {
        saving.value = true
        try {
            const { data } = await api.post(path, payload)
            await fetch()
            return data
        } finally {
            saving.value = false
        }
    }

    function goPage(page) {
        params.page = page
        fetch()
    }

    return { items, meta, loading, saving, deleting, errors, params, fetch, create, update, remove, action, goPage }
}
