<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { useCartStore } from '../stores/cart'
import { useAuthStore } from '../stores/auth'
import AuthModal from '../components/AuthModal.vue'
import CartDrawer from '../components/CartDrawer.vue'
import { Search, ShoppingBag, User, Store, LogOut, Sparkles, Truck, ShieldCheck } from 'lucide-vue-next'

const router = useRouter()
const cart = useCartStore()
const auth = useAuthStore()
const isAuthOpen = ref(false)
const products = ref([])
const categories = ref([])
const selectedCategory = ref(null)
const searchQuery = ref('')
const isLoading = ref(true)
const isLoadingMore = ref(false)
const hasMore = ref(true)
const page = ref(1)
const pageSize = 24
let requestId = 0

const loadData = async (reset = true) => {
  const id = ++requestId
  if (reset) { page.value = 1; hasMore.value = true; isLoading.value = true }
  else isLoadingMore.value = true
  try {
    const params = { page: page.value, per_page: pageSize }
    if (selectedCategory.value) params.category_id = selectedCategory.value
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim()
    const requests = [axios.get('/products', { params })]
    if (reset) requests.push(axios.get('/categories'))
    const [productsRes, categoriesRes] = await Promise.all(requests)
    if (id !== requestId) return
    const payload = productsRes.data
    const items = Array.isArray(payload) ? payload : (payload.data || [])
    products.value = reset ? items : [...products.value, ...items]
    hasMore.value = payload?.next_page_url != null || (payload?.current_page < payload?.last_page)
    if (reset && categoriesRes) categories.value = categoriesRes.data?.data || categoriesRes.data || []
  } catch (err) {
    if (reset) products.value = []
    console.error('Erro ao carregar dados da vitrine:', err)
  } finally { isLoading.value = false; isLoadingMore.value = false }
}
const loadMore = async () => { if (!isLoadingMore.value && hasMore.value) { page.value++; await loadData(false) } }
const filteredProducts = computed(() => products.value)
let searchTimer
watch([searchQuery, selectedCategory], () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadData(), 250) })
const formatMoney = cents => ((cents || 0) / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
const handleLogout = () => { auth.logout(); router.push('/') }
onMounted(() => {
  loadData()
  const observer = new IntersectionObserver(entries => { if (entries[0].isIntersecting) loadMore() }, { rootMargin: '500px' })
  nextTick(() => { const sentinel = document.querySelector('.catalog-sentinel'); if (sentinel) observer.observe(sentinel) })
})
</script>

<template>
  <div class="storefront min-h-screen text-slate-800">
    <header class="store-header sticky top-0 z-40">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 h-20 flex items-center gap-5">
        <button class="brand shrink-0" @click="selectedCategory = null; searchQuery = ''" aria-label="Ir para início">
          <span class="brand-mark"><Sparkles class="w-5 h-5" /></span><span><strong>Nexus</strong><small>commerce</small></span>
        </button>
        <div class="search-box flex-1 max-w-2xl hidden sm:block"><Search class="w-5 h-5" /><input v-model="searchQuery" placeholder="O que você está procurando?" /></div>
        <div class="ml-auto flex items-center gap-2">
          <button v-if="auth.user && (auth.user.role === 'SELLER' || auth.user.role === 'ADMIN')" @click="router.push('/seller')" class="header-action hidden lg:flex"><Store class="w-4 h-4" /> Painel</button>
          <button @click="cart.openCart" class="header-icon relative" aria-label="Abrir carrinho"><ShoppingBag class="w-5 h-5" /><b v-if="cart.itemCount">{{ cart.itemCount }}</b></button>
          <button v-if="auth.user" @click="router.push('/profile')" class="user-action hidden md:flex"><User class="w-4 h-4" /> {{ auth.user.name }}</button>
          <button v-else @click="isAuthOpen = true" class="login-button"><User class="w-4 h-4" /> Entrar</button>
          <button v-if="auth.user" @click="handleLogout" class="header-icon hidden sm:flex" title="Sair"><LogOut class="w-4 h-4" /></button>
        </div>
      </div>
      <div class="mobile-search sm:hidden px-4 pb-3"><div class="search-box"><Search class="w-4 h-4" /><input v-model="searchQuery" placeholder="Buscar produtos" /></div></div>
    </header>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
      <nav class="category-row" aria-label="Categorias"><button :class="{ active: !selectedCategory }" @click="selectedCategory = null">Todos</button><button v-for="cat in categories" :key="cat.id" :class="{ active: selectedCategory === cat.id }" @click="selectedCategory = cat.id">{{ cat.name }}</button></nav>
      <section class="hero-banner"><div><span class="eyebrow">NOVIDADES NA NEXUS</span><h1>Do seu jeito.<br><em>Do seu mundo.</em></h1><p>Encontre produtos incríveis, preços especiais e entrega segura para todo o Brasil.</p><button @click="searchQuery = ''" class="hero-button">Ver ofertas <span>→</span></button></div><div class="hero-art"><ShoppingBag class="w-28 h-28" /></div></section>
      <div class="benefits"><div><Truck /><span><b>Entrega para todo Brasil</b><small>Rápida e segura</small></span></div><div><ShieldCheck /><span><b>Compra protegida</b><small>Seus dados sempre seguros</small></span></div><div><Sparkles /><span><b>Produtos selecionados</b><small>Qualidade que você confia</small></span></div></div>
      <div class="section-heading"><div><span class="eyebrow">ESCOLHAS PARA VOCÊ</span><h2>Produtos em destaque</h2></div><span class="result-count">{{ filteredProducts.length }} produtos</span></div>
      <div v-if="isLoading" class="product-grid"><div v-for="n in 8" :key="n" class="product-card skeleton-card"><div class="product-image skeleton"></div><div class="product-info"><div class="skeleton line"></div><div class="skeleton line short"></div></div></div></div>
      <div v-else-if="!filteredProducts.length" class="empty-state"><ShoppingBag class="w-10 h-10 mx-auto mb-3" /><b>Nenhum produto encontrado</b><p>Tente buscar por outro termo ou categoria.</p></div>
      <div v-else class="product-grid"><article v-for="product in filteredProducts" :key="product.id" class="product-card" @click="router.push(`/products/${product.id}`)"><div class="product-image"><img loading="lazy" :src="(product.images && product.images[0]) || product.image_url" :alt="product.name" @error="$event.target.style.display='none'" /><span v-if="product.original_price_cents > product.price_cents" class="discount">OFERTA</span></div><div class="product-info"><small>{{ product.category?.name || 'Nexus' }}</small><h3>{{ product.name }}</h3><p>{{ product.description || 'Produto oficial com garantia e envio imediato.' }}</p><div class="product-bottom"><strong>{{ formatMoney(product.price_cents) }}</strong><button @click.stop="cart.addItem(product)" aria-label="Adicionar ao carrinho"><ShoppingBag class="w-4 h-4" /></button></div></div></article></div>
    <div class="catalog-sentinel" aria-hidden="true"></div><div v-if="isLoadingMore" class="loading-more">Carregando mais produtos...</div>
     </main>
    <AuthModal :isOpen="isAuthOpen" @close="isAuthOpen = false" /><CartDrawer />
  </div>
</template>
