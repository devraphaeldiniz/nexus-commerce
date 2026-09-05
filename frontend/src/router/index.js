import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

import StorefrontView from '../views/StorefrontView.vue'
import LoginView from '../views/LoginView.vue'
import ProfileView from '../views/ProfileView.vue'
import SellerDashboard from '../components/SellerDashboard.vue'
import LogisticsHub from '../components/LogisticsHub.vue'
import DisputeCenter from '../components/DisputeCenter.vue'

const routes = [
  {
    path: '/',
    name: 'storefront',
    component: StorefrontView,
  },
  {
    path: '/login',
    name: 'login',
    component: LoginView,
  },
  {
    path: '/profile',
    name: 'profile',
    component: ProfileView,
    meta: { requiresAuth: true },
  },
  {
    path: '/seller',
    name: 'seller',
    component: SellerDashboard,
    meta: { requiresAuth: true, roles: ['SELLER', 'ADMIN'] },
  },
  {
    path: '/logistics',
    name: 'logistics',
    component: LogisticsHub,
    meta: { requiresAuth: true, roles: ['LOGISTICS', 'ADMIN'] },
  },
  {
    path: '/disputes',
    name: 'disputes',
    component: DisputeCenter,
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return next({ name: 'login', query: { redirect: to.fullPath } })
  }

  if (to.meta.roles && !to.meta.roles.includes(auth.currentRole)) {
    alert('Acesso negado: seu perfil não tem permissão para acessar esta área.')
    return next({ name: 'storefront' })
  }

  next()
})

export default router
