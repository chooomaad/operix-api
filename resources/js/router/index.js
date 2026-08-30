import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('../views/auth/Login.vue'),
        meta: { public: true },
    },
    {
        path: '/',
        component: () => import('../layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            { path: '',              redirect: '/dashboard' },
            { path: 'dashboard',     name: 'dashboard',     component: () => import('../views/Dashboard.vue') },
            { path: 'employees',     name: 'employees',     component: () => import('../views/employees/Index.vue') },
            { path: 'employees/:id', name: 'employee-show', component: () => import('../views/employees/Show.vue') },
            { path: 'notifications', name: 'notifications', component: () => import('../views/Notifications.vue') },
            // Admin-only routes
            { path: 'users',         name: 'users',         component: () => import('../views/Users.vue'),            meta: { adminOnly: true } },
            { path: 'incidents',     name: 'incidents',     component: () => import('../views/hsse/Incidents.vue'),   meta: { adminOnly: true } },
            { path: 'near-miss',     name: 'near-miss',     component: () => import('../views/hsse/NearMiss.vue'),    meta: { adminOnly: true } },
            { path: 'environment',   name: 'environment',   component: () => import('../views/hsse/Environment.vue'), meta: { adminOnly: true } },
            { path: 'breaches',      name: 'breaches',      component: () => import('../views/hsse/Breaches.vue'),    meta: { adminOnly: true } },
            { path: 'visitors',      name: 'visitors',      component: () => import('../views/Visitors.vue'),         meta: { adminOnly: true } },
            { path: 'contractors',   name: 'contractors',   component: () => import('../views/Contractors.vue'),      meta: { adminOnly: true } },
            { path: 'equipment',     name: 'equipment',     component: () => import('../views/Equipment.vue'),        meta: { adminOnly: true } },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
})

router.beforeEach((to) => {
    const auth = useAuthStore()

    if (to.meta.requiresAuth && !auth.isAuthenticated) return '/login'
    if (to.meta.public && auth.isAuthenticated)        return '/dashboard'

    // Bloquer l'agent sur les routes adminOnly
    if (to.meta.adminOnly && auth.user?.role === 'agent') return '/dashboard'
})

export default router
