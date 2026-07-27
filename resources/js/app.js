import '../css/app.css'

import { createApp }   from 'vue'
import { createPinia } from 'pinia'
import { i18n }        from './i18n'
import router          from './router'
import App             from './App.vue'
import { useAuthStore } from './stores/auth'
import { useNotificationStore } from './stores/notifications'
import { createEcho }  from './api/echo'

const app   = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)
app.use(i18n)

// Connecter Echo/Reverb après l'init Pinia
router.isReady().then(() => {
    const auth = useAuthStore()
    if (auth.token) {
        const echo        = createEcho(auth.token)
        const notifStore  = useNotificationStore()

        echo.private(`user.${auth.user?.id}`)
            .listen('.notification.sent', (payload) => {
                notifStore.addRealtime(payload)
            })
    }
})

app.mount('#app')
