import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '../api/client'

export const useAuthStore = defineStore('auth', () => {
    const token = ref(localStorage.getItem('operix_token') || null)
    const user  = ref(JSON.parse(localStorage.getItem('operix_user') || 'null'))

    const isAuthenticated = computed(() => !!token.value)

    async function requestOtp(email) {
        await api.post('/auth/request-otp', { email })
    }

    async function verifyOtp(email, code) {
        const { data } = await api.post('/auth/verify-otp', { email, code })
        token.value = data.token
        user.value  = data.user
        localStorage.setItem('operix_token', data.token)
        localStorage.setItem('operix_user', JSON.stringify(data.user))
    }

    async function logout() {
        try { await api.post('/auth/logout') } catch { /* no-op */ }
        token.value = null
        user.value  = null
        localStorage.removeItem('operix_token')
        localStorage.removeItem('operix_user')
    }

    async function fetchMe() {
        const { data } = await api.get('/auth/me')
        user.value = data.user ?? data
        localStorage.setItem('operix_user', JSON.stringify(user.value))
    }

    return { token, user, isAuthenticated, requestOtp, verifyOtp, logout, fetchMe }
})
