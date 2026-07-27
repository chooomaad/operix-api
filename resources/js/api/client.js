import axios from 'axios'

const api = axios.create({
    baseURL: '/api',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
})

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('operix_token')
    if (token) config.headers.Authorization = `Bearer ${token}`
    return config
})

api.interceptors.response.use(
    (res) => res,
    (err) => {
        if (err.response?.status === 401) {
            localStorage.removeItem('operix_token')
            localStorage.removeItem('operix_user')
            window.location.href = '/login'
        }
        return Promise.reject(err)
    }
)

export default api
