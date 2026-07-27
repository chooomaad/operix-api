import api from '../api/client'

export function useFetch() {
    async function get(url, params = {}) {
        const { data } = await api.get(url, { params })
        return data
    }

    async function post(url, payload = {}) {
        const { data } = await api.post(url, payload)
        return data
    }

    async function put(url, payload = {}) {
        const { data } = await api.put(url, payload)
        return data
    }

    async function del(url) {
        const { data } = await api.delete(url)
        return data
    }

    return { get, post, put, del }
}
