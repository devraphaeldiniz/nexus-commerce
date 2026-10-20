<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useCartStore } from '../stores/cart'
import { useAuthStore } from '../stores/auth'
import AuthModal from '../components/AuthModal.vue'
import CartDrawer from '../components/CartDrawer.vue'
import { 
  Search, 
  ShoppingBag, 
  User, 
  Store, 
  LogOut, 
  ShieldCheck, 
  Sparkles 
} from 'lucide-vue-next'

const router = useRouter()
const cart = useCartStore()
const auth = useAuthStore()

const isAuthOpen = ref(false)
const isCartOpen = ref(false)

const products = ref([])
const categories = ref([])
const selectedCategory = ref(null)
const searchQuery = ref('')
const isLoading = ref(true)

const loadData = async () => {
  isLoading.value = true
  try {
    const [productsRes, categoriesRes] = await Promise.all([
      axios.get('/products'),
      axios.get('/categories')
    ])

    const rawProducts = productsRes.data?.data || productsRes.data
    products.value = Array.isArray(rawProducts) ? rawProducts : []

    const rawCategories = categoriesRes.data?.data || categoriesRes.data
    categories.value = Array.isArray(rawCategories) ? rawCategories : []
  } catch (err) {
    console.error('Erro ao carregar dados da vitrine:', err)
    products.value = []
    categories.value = []
  } finally {
    isLoading.value = false
  }
}

const filteredProducts = computed(() => {
  if (!Array.isArray(products.value)) return []

  return products.value.filter(p => {
    const matchesCategory = !selectedCategory.value || p.category_id === selectedCategory.value
    const matchesSearch = !searchQuery.value || (p.name && p.name.toLowerCase().includes(searchQuery.value.toLowerCase()))
    return matchesCategory && matchesSearch
  })
})

const formatMoney = (cents) => {
  return ((cents || 0) / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}

const handleLogout = () => {
  auth.logout()
  router.push('/')
}

onMounted(() => {
  loadData()
})
</script>

<template>
  <div class="min-h-screen bg-neutral-950 text-white selection:bg-indigo-500 selection:text-white">
    <!-- NAVBAR SUPERIOR -->
    <header class="sticky top-0 z-40 bg-neutral-950/80 backdrop-blur-md border-b border-neutral-800/80">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        
        <!-- Logo -->
        <div class="flex items-center gap-3 cursor-pointer" @click="selectedCategory = null; searchQuery = ''">
          <div class="w-10 h-10 rounded-2xl bg-indigo-600/20 border border-indigo-500/30 flex items-center justify-center text-indigo-400 shadow-lg shadow-indigo-600/10">
            <Sparkles class="w-5 h-5" />
          </div>
          <div class="flex flex-col">
            <span class="text-base font-black tracking-wider uppercase font-mono bg-gradient-to-r from-white via-neutral-200 to-indigo-400 bg-clip-text text-transparent">
              Nexus Commerce
            </span>
            <span class="text-[10px] text-neutral-400 -mt-1 font-mono">Next-Gen Marketplace</span>
          </div>
        </div>

        <!-- Ações do Topo -->
        <div class="flex items-center gap-3">
          <!-- Botão Portal Seller (se for SELLER ou ADMIN) -->
          <button 
            v-if="auth.user && (auth.user.role === 'SELLER' || auth.user.role === 'ADMIN')"
            @click="router.push('/seller')"
            class="hidden sm:flex items-center gap-2 px-3.5 py-2 rounded-xl bg-indigo-600/15 border border-indigo-500/30 text-indigo-300 text-xs font-bold hover:bg-indigo-600/25 transition cursor-pointer"
          >
            <Store class="w-4 h-4 text-indigo-400" />
            <span>Painel Seller</span>
          </button>

          <!-- Carrinho -->
          <button 
            @click="isCartOpen = true"
            class="relative p-2.5 rounded-xl bg-neutral-900 border border-neutral-800 hover:border-neutral-700 text-neutral-300 hover:text-white transition cursor-pointer"
            title="Abrir Carrinho"
          >
            <ShoppingBag class="w-4 h-4" />
            <span 
              v-if="cart.itemCount > 0"
              class="absolute -top-1 -right-1 w-5 h-5 rounded-full bg-indigo-600 text-white text-[10px] font-black flex items-center justify-center border-2 border-neutral-950"
            >
              {{ cart.itemCount }}
            </span>
          </button>

          <!-- Bloco do Usuário (Logado ou Deslogado) -->
          <template v-if="auth.user">
            <div class="flex items-center gap-2 pl-2 border-l border-neutral-800">
              <div class="flex flex-col text-right hidden md:block">
                <span class="text-xs font-bold text-white">{{ auth.user.name }}</span>
                <span class="text-[10px] font-mono text-indigo-400 font-bold uppercase">{{ auth.user.role }}</span>
              </div>
              <button 
                @click="handleLogout"
                class="p-2.5 rounded-xl bg-neutral-900 border border-neutral-800 hover:border-rose-900 hover:text-rose-400 text-neutral-400 transition cursor-pointer"
                title="Sair da Conta"
              >
                <LogOut class="w-4 h-4" />
              </button>
            </div>
          </template>

          <template v-else>
            <button 
              @click="isAuthOpen = true"
              class="flex items-center gap-2 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-lg shadow-indigo-600/20 cursor-pointer"
            >
              <User class="w-4 h-4" />
              <span>Entrar / Cadastrar</span>
            </button>
          </template>
        </div>

      </div>
    </header>

    <!-- CORPO PRINCIPAL -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
      
      <!-- Pesquisa e Filtros -->
      <div class="flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="relative w-full md:w-96">
          <Search class="w-4 h-4 text-neutral-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input 
            v-model="searchQuery" 
            type="text" 
            placeholder="Buscar produtos, periféricos, hardware..." 
            class="w-full bg-neutral-900 border border-neutral-800 focus:border-indigo-500 rounded-2xl pl-10 pr-4 py-2.5 text-xs text-white placeholder-neutral-500 focus:outline-hidden transition"
          />
        </div>

        <div class="flex items-center gap-1.5 overflow-x-auto w-full md:w-auto pb-1 scrollbar-none">
          <button 
            @click="selectedCategory = null" 
            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition cursor-pointer"
            :class="!selectedCategory ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-neutral-900 border border-neutral-800 text-neutral-400 hover:text-white'"
          >
            Todos
          </button>

          <button 
            v-for="cat in categories" 
            :key="cat.id" 
            @click="selectedCategory = cat.id" 
            class="px-3.5 py-1.5 rounded-xl text-xs font-semibold whitespace-nowrap transition cursor-pointer"
            :class="selectedCategory === cat.id ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20' : 'bg-neutral-900 border border-neutral-800 text-neutral-400 hover:text-white'"
          >
            {{ cat.name }}
          </button>
        </div>
      </div>

      <!-- Loading -->
      <div v-if="isLoading" class="text-center py-20 text-xs text-neutral-500">
        Carregando vitrine...
      </div>

      <!-- Vitrine Vazia -->
      <div v-else-if="filteredProducts.length === 0" class="text-center py-20 bg-neutral-900/40 border border-neutral-800 rounded-3xl p-8 space-y-3">
        <ShoppingBag class="w-12 h-12 text-neutral-600 mx-auto stroke-1" />
        <h3 class="text-sm font-bold text-white">Nenhum produto anunciado</h3>
        <p class="text-xs text-neutral-400">Cadastre anúncios pelo Portal Seller para publicá-los na loja.</p>
      </div>

      <!-- Lista de Produtos -->
      <div v-else class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
        <div 
          v-for="product in filteredProducts" 
          :key="product.id" 
          class="group bg-neutral-900 border border-neutral-800 hover:border-indigo-500/40 rounded-3xl p-4 flex flex-col justify-between space-y-4 transition duration-200 shadow-md cursor-pointer"
          @click="router.push(`/products/${product.id}`)"
        >
          <div class="space-y-3">
            <!-- Imagem com Desconto -->
            <div class="relative w-full aspect-square rounded-2xl overflow-hidden bg-neutral-950 border border-neutral-800/80">
              <img 
                :src="(product.images && product.images[0]) || product.image_url" 
                :alt="product.name" 
                class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
              />
              
              <span 
                v-if="product.original_price_cents && product.original_price_cents > product.price_cents" 
                class="absolute top-2.5 left-2.5 bg-rose-600 text-white text-[10px] font-black px-2 py-0.5 rounded-lg uppercase tracking-wider shadow-md"
              >
                -{{ Math.round(((product.original_price_cents - product.price_cents) / product.original_price_cents) * 100) }}% OFF
              </span>
            </div>

            <div class="space-y-1">
              <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider font-mono">
                {{ product.category?.name || 'Nexus' }}
              </span>
              <h3 class="text-sm font-bold text-white line-clamp-1 group-hover:text-indigo-300 transition" :title="product.name">
                {{ product.name }}
              </h3>
              <p class="text-[11px] text-neutral-400 line-clamp-2 leading-relaxed">
                {{ product.description || 'Produto oficial com garantia e envio imediato.' }}
              </p>
            </div>
          </div>

          <div class="space-y-3 pt-2 border-t border-neutral-800/80" @click.stop>
            <div class="flex items-baseline gap-2">
              <span class="text-lg font-black text-white font-mono">{{ formatMoney(product.price_cents) }}</span>
              <span 
                v-if="product.original_price_cents && product.original_price_cents > product.price_cents" 
                class="text-xs text-neutral-500 line-through font-mono"
              >
                {{ formatMoney(product.original_price_cents) }}
              </span>
            </div>

            <button 
              @click.stop="cart.addItem(product)" 
              class="w-full bg-neutral-950 hover:bg-indigo-600 border border-neutral-800 hover:border-indigo-500 text-white text-xs font-bold py-2.5 rounded-xl transition flex items-center justify-center gap-2 cursor-pointer group-hover:bg-indigo-600"
            >
              <ShoppingBag class="w-3.5 h-3.5" />
              <span>Adicionar ao Carrinho</span>
            </button>
          </div>
        </div>
      </div>

    </main>

    <!-- Modais Globais -->
    <AuthModal :isOpen="isAuthOpen" @close="isAuthOpen = false" />
    <CartDrawer :isOpen="isCartOpen" @close="isCartOpen = false" />
  </div>
</template>
