import { createRouter, createWebHistory } from 'vue-router'
import StorefrontView from '../views/StorefrontView.vue'
import ProductDetailView from '../views/ProductDetailView.vue'
import OrdersView from '../views/OrdersView.vue'
import SellerDashboardView from '../views/SellerDashboardView.vue'
import LoginView from '../views/LoginView.vue'
import ProfileView from '../views/ProfileView.vue'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/', component: StorefrontView },
  { path: '/products/:id', component: ProductDetailView },
  { path: '/login', component: LoginView },
  { path: '/profile', component: ProfileView, meta: { requiresAuth: true } },
  { path: '/orders', component: OrdersView, meta: { requiresAuth: true } },
  { 
    path: '/seller', 
    component: SellerDashboardView,
    meta: { requiresSeller: true }
  },
]

export const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to, from, next) => {
  const auth = useAuthStore()
  if (to.meta.requiresAuth && !auth.user) {
    return next({ path: '/login', query: { redirect: to.fullPath } })
  }
  if (to.meta.requiresSeller) {
    const auth = useAuthStore()
    if (!auth.user || (auth.user.role !== 'SELLER' && auth.user.role !== 'ADMIN')) {
      alert('Acesso restrito: Esta área é exclusiva para vendedores homologados.')
      return next('/')
    }
  }
  next()
})

export default router
