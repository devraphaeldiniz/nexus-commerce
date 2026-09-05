<script setup>
import { onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useCartStore } from './stores/cart'
import CartDrawer from './components/CartDrawer.vue'
import { 
  Box, 
  Store, 
  LayoutDashboard, 
  Truck, 
  ShieldAlert, 
  ShoppingBag, 
  LogIn, 
  LogOut, 
  User 
} from 'lucide-vue-next'

const auth = useAuthStore()
const cart = useCartStore()
const router = useRouter()
const route = useRoute()

onMounted(() => {
  auth.initAuth()
})

const handleLogout = async () => {
  await auth.logout()
  router.push('/login')
}
</script>

<template>
  <div class="min-h-screen bg-neutral-950 text-neutral-100 flex flex-col font-sans">
    <!-- Navbar Global com Rotas Reais -->
    <header class="sticky top-0 z-40 bg-neutral-950/80 backdrop-blur-md border-b border-neutral-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        
        <!-- Logo e Links de Navegação Reais -->
        <div class="flex items-center gap-6">
          <router-link to="/" class="flex items-center gap-3">
            <div class="bg-indigo-600 p-2 rounded-xl text-white shadow-md shadow-indigo-600/30">
              <Box class="w-5 h-5" />
            </div>
            <span class="font-bold text-lg tracking-tight text-white hidden sm:inline">
              NEXUS<span class="text-indigo-400">COMMERCE</span>
            </span>
          </router-link>

          <nav class="hidden md:flex items-center gap-1 bg-neutral-900 border border-neutral-800 p-1 rounded-xl">
            <router-link 
              to="/" 
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition"
              :class="route.path === '/' ? 'bg-indigo-600 text-white' : 'text-neutral-400 hover:text-white'"
            >
              <Store class="w-3.5 h-3.5" />
              <span>Vitrine</span>
            </router-link>

            <!-- Apenas SELLER ou ADMIN -->
            <router-link 
              v-if="auth.isSeller || auth.isAdmin"
              to="/seller" 
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition"
              :class="route.path === '/seller' ? 'bg-indigo-600 text-white' : 'text-neutral-400 hover:text-white'"
            >
              <LayoutDashboard class="w-3.5 h-3.5" />
              <span>Painel do Vendedor</span>
            </router-link>

            <!-- Apenas LOGISTICS ou ADMIN -->
            <router-link 
              v-if="auth.isLogistics || auth.isAdmin"
              to="/logistics" 
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition"
              :class="route.path === '/logistics' ? 'bg-amber-600 text-white' : 'text-neutral-400 hover:text-white'"
            >
              <Truck class="w-3.5 h-3.5" />
              <span>Fulfillment & WMS</span>
            </router-link>

            <router-link 
              to="/disputes" 
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition"
              :class="route.path === '/disputes' ? 'bg-rose-600 text-white' : 'text-neutral-400 hover:text-white'"
            >
              <ShieldAlert class="w-3.5 h-3.5" />
              <span>Mediação & SAC</span>
            </router-link>
          </nav>
        </div>

        <!-- Área de Autenticação Real & Carrinho -->
        <div class="flex items-center gap-3">
          <!-- Usuário Autenticado -->
          <div v-if="auth.isAuthenticated" class="flex items-center gap-2">
            <router-link 
              to="/profile"
              class="flex items-center gap-2 bg-neutral-900 hover:bg-neutral-850 border border-neutral-800 hover:border-neutral-700 px-3 py-1.5 rounded-xl transition"
              title="Acessar Meu Perfil"
            >
              <User class="w-3.5 h-3.5 text-indigo-400" />
              <div class="text-left hidden lg:block">
                <p class="text-xs font-bold text-white leading-tight">{{ auth.user?.name }}</p>
                <p class="text-[10px] text-neutral-400 leading-tight">{{ auth.user?.email }}</p>
              </div>
              <span 
                class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase ml-1"
                :class="{
                  'bg-emerald-500/20 text-emerald-300': auth.isSeller,
                  'bg-indigo-500/20 text-indigo-300': auth.isCustomer,
                  'bg-amber-500/20 text-amber-300': auth.isLogistics,
                  'bg-rose-500/20 text-rose-300': auth.isAdmin
                }"
              >
                {{ auth.currentRole }}
              </span>
            </router-link>

            <button 
              @click="handleLogout"
              title="Encerrar Sessão"
              class="p-2 bg-neutral-900 hover:bg-rose-950/40 hover:text-rose-400 text-neutral-400 border border-neutral-800 rounded-xl transition"
            >
              <LogOut class="w-4 h-4" />
            </button>
          </div>

          <!-- Usuário Anônimo -->
          <router-link 
            v-else
            to="/login"
            class="flex items-center gap-1.5 text-xs bg-indigo-600 hover:bg-indigo-500 text-white font-semibold px-3.5 py-2 rounded-xl transition shadow-md shadow-indigo-600/20"
          >
            <LogIn class="w-3.5 h-3.5" />
            <span>Fazer Login</span>
          </router-link>

          <!-- Botão do Carrinho -->
          <button 
            @click="cart.toggleCart"
            class="relative p-2.5 bg-neutral-900 border border-neutral-800 hover:border-neutral-700 rounded-xl text-neutral-300 hover:text-white transition flex items-center gap-2"
          >
            <ShoppingBag class="w-5 h-5" />
            <span class="text-xs font-semibold pr-1 hidden sm:inline">{{ cart.formattedTotal }}</span>
            <span 
              v-if="cart.totalItemsCount > 0" 
              class="absolute -top-1.5 -right-1.5 bg-indigo-600 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center shadow"
            >
              {{ cart.totalItemsCount }}
            </span>
          </button>
        </div>
      </div>
    </header>

    <main class="flex-1 max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">
      <router-view />
    </main>

    <CartDrawer />
  </div>
</template>
