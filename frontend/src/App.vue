<script setup>
import { ref, computed, onMounted } from 'vue'

const API_BASE_URL = 'https://pleasant-light-production.up.railway.app'

const products = ref([])
const isLoading = ref(true)
const apiStatus = ref('conectando')

const searchQuery = ref('')
const cart = ref([])
const isCartOpen = ref(false)

// Modais
const isAuthModalOpen = ref(false)
const isControlPanelOpen = ref(false)
const activeTab = ref('products') // 'products', 'sellers', 'profile', 'security'

// Autenticação
const authMode = ref('login')
const email = ref('')
const password = ref('')
const name = ref('')
const storeName = ref('')
const isSeller = ref(false)
const authLoading = ref(false)
const authError = ref('')
const currentUser = ref(JSON.parse(localStorage.getItem('nexus_user') || 'null'))
const authToken = ref(localStorage.getItem('nexus_token') || '')

// Formulário de Perfil / CPF
const profileForm = ref({
  name: currentUser.value?.name || '',
  email: currentUser.value?.email || '',
  cpf: currentUser.value?.cpf || ''
})
const profileMsg = ref('')
const profileLoading = ref(false)

// Formulário de Alteração de Senha
const passForm = ref({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
})
const passMsg = ref('')
const passLoading = ref(false)

// Cadastro de Produto
const newProd = ref({
  title: '',
  price: '',
  stock_quantity: 10,
  category_id: 1,
  description: '',
  image_url: ''
})
const prodLoading = ref(false)
const prodMsg = ref('')

// Admin - Moderação de Vendedores
const pendingSellers = ref([])
const adminLoading = ref(false)

// 1. Carregar Vitrine
const fetchProducts = async () => {
  isLoading.value = true
  try {
    const res = await fetch(`${API_BASE_URL}/api/products`, {
      headers: { 'Accept': 'application/json' }
    })
    if (!res.ok) throw new Error('Status ' + res.status)
    const data = await res.json()
    products.value = Array.isArray(data) ? data : (data.data || [])
    apiStatus.value = 'online'
  } catch (err) {
    console.error(err)
    apiStatus.value = 'offline'
  } finally {
    isLoading.value = false
  }
}

// 2. Login e Registro
const handleAuth = async () => {
  authLoading.value = true
  authError.value = ''

  const endpoint = authMode.value === 'login' ? '/api/login' : '/api/register'
  const payload = authMode.value === 'login'
    ? { email: email.value, password: password.value }
    : {
        name: name.value,
        email: email.value,
        password: password.value,
        role: isSeller.value ? 'SELLER' : 'CUSTOMER',
        store_name: storeName.value || `${name.value} Store`
      }

  try {
    const res = await fetch(`${API_BASE_URL}${endpoint}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.message || 'Falha ao autenticar')

    currentUser.value = data.user
    authToken.value = data.token || ''
    localStorage.setItem('nexus_user', JSON.stringify(data.user))
    localStorage.setItem('nexus_token', authToken.value)
    
    // Sincroniza dados do perfil
    profileForm.value.name = data.user.name || ''
    profileForm.value.email = data.user.email || ''
    profileForm.value.cpf = data.user.cpf || ''

    isAuthModalOpen.value = false
  } catch (err) {
    authError.value = err.message
  } finally {
    authLoading.value = false
  }
}

const logout = () => {
  currentUser.value = null
  authToken.value = ''
  localStorage.removeItem('nexus_user')
  localStorage.removeItem('nexus_token')
  isControlPanelOpen.value = false
}

// 3. Atualizar Dados do Perfil / CPF
const handleUpdateProfile = async () => {
  profileLoading.value = true
  profileMsg.value = ''
  try {
    const res = await fetch(`${API_BASE_URL}/api/user/profile`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authToken.value}`
      },
      body: JSON.stringify(profileForm.value)
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.message || 'Erro ao atualizar dados')

    currentUser.value = { ...currentUser.value, ...data.user }
    localStorage.setItem('nexus_user', JSON.stringify(currentUser.value))
    profileMsg.value = 'Informações e CPF atualizados com sucesso!'
  } catch (err) {
    profileMsg.value = err.message
  } finally {
    profileLoading.value = false
  }
}

// 4. Alterar Senha
const handleUpdatePassword = async () => {
  passLoading.value = true
  passMsg.value = ''
  try {
    const res = await fetch(`${API_BASE_URL}/api/user/password`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authToken.value}`
      },
      body: JSON.stringify(passForm.value)
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.message || 'Erro ao alterar senha')

    passMsg.value = 'Senha alterada com sucesso!'
    passForm.value = { current_password: '', new_password: '', new_password_confirmation: '' }
  } catch (err) {
    passMsg.value = err.message
  } finally {
    passLoading.value = false
  }
}

// 5. Cadastrar Produto
const handleCreateProduct = async () => {
  prodLoading.value = true
  prodMsg.value = ''
  try {
    const res = await fetch(`${API_BASE_URL}/api/seller/products`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'Authorization': `Bearer ${authToken.value}`
      },
      body: JSON.stringify({
        title: newProd.value.title,
        price: parseFloat(newProd.value.price),
        stock_quantity: parseInt(newProd.value.stock_quantity) || 1,
        category_id: newProd.value.category_id,
        description: newProd.value.description,
        image_url: newProd.value.image_url
      })
    })
    const data = await res.json()
    if (!res.ok) throw new Error(data.message || 'Erro ao publicar produto')

    prodMsg.value = 'Produto publicado com sucesso no Nexus!'
    newProd.value = { title: '', price: '', stock_quantity: 10, category_id: 1, description: '', image_url: '' }
    fetchProducts()
  } catch (err) {
    prodMsg.value = err.message
  } finally {
    prodLoading.value = false
  }
}

// 6. Listar e Aprovar Vendedores
const fetchPendingSellers = async () => {
  adminLoading.value = true
  try {
    const res = await fetch(`${API_BASE_URL}/api/admin/sellers/pending`, {
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${authToken.value}`
      }
    })
    if (res.ok) {
      pendingSellers.value = await res.json()
    }
  } catch (err) {
    console.error(err)
  } finally {
    adminLoading.value = false
  }
}

const approveSeller = async (id) => {
  try {
    const res = await fetch(`${API_BASE_URL}/api/admin/sellers/${id}/approve`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${authToken.value}`
      }
    })
    if (res.ok) {
      pendingSellers.value = pendingSellers.value.filter(s => s.id !== id)
    }
  } catch (err) {
    alert('Erro ao aprovar vendedor')
  }
}

const openControlPanel = (tab = 'products') => {
  activeTab.value = tab
  isControlPanelOpen.value = true
  if (currentUser.value?.role === 'ADMIN') {
    fetchPendingSellers()
  }
}

onMounted(() => {
  fetchProducts()
})

const filteredProducts = computed(() => {
  return products.value.filter(p => {
    const nameMatch = (p.title || p.name || '').toLowerCase().includes(searchQuery.value.toLowerCase())
    const descMatch = (p.description || '').toLowerCase().includes(searchQuery.value.toLowerCase())
    return nameMatch || descMatch
  })
})

const cartTotal = computed(() => cart.value.reduce((acc, item) => acc + (Number(item.price) * item.quantity), 0))
const cartCount = computed(() => cart.value.reduce((acc, item) => acc + item.quantity, 0))

const addToCart = (product) => {
  const existing = cart.value.find(item => item.id === product.id)
  if (existing) existing.quantity++
  else cart.value.push({ ...product, quantity: 1 })
  isCartOpen.value = true
}

const removeFromCart = (id) => {
  cart.value = cart.value.filter(item => item.id !== id)
}
</script>

<template>
  <div class="min-h-screen flex flex-col bg-[#090D16] text-slate-100 font-sans">
    <!-- Barra Superior -->
    <div class="bg-gradient-to-r from-purple-600 via-indigo-600 to-cyan-500 py-1.5 px-4 text-xs font-bold uppercase tracking-wider text-white shadow-lg flex justify-between items-center max-w-7xl mx-auto w-full">
      <span>⚡ Nexus Marketplace • Operações & Painel Administrativo Unificado</span>
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full" :class="apiStatus === 'online' ? 'bg-emerald-400' : 'bg-amber-400'"></span>
        <span class="text-[10px] font-mono lowercase">api: {{ apiStatus }}</span>
      </div>
    </div>

    <!-- Header / Navbar -->
    <header class="sticky top-0 z-40 backdrop-blur-xl bg-[#0B1120]/85 border-b border-slate-800/80">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4">
        
        <!-- Marca -->
        <div class="flex items-center gap-3 cursor-pointer group">
          <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-purple-600 to-cyan-400 p-[2px] shadow-lg shadow-purple-500/20">
            <div class="w-full h-full bg-[#0B1120] rounded-[10px] flex items-center justify-center">
              <svg class="w-6 h-6 text-cyan-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                <polyline points="2 17 12 22 22 17"></polyline>
                <polyline points="2 12 12 17 22 12"></polyline>
              </svg>
            </div>
          </div>
          <div class="flex flex-col">
            <span class="text-2xl font-extrabold tracking-wider bg-gradient-to-r from-white via-slate-200 to-cyan-400 bg-clip-text text-transparent">
              NEXUS
            </span>
            <span class="text-[9px] uppercase tracking-[0.25em] text-purple-400 font-bold -mt-1">Commerce</span>
          </div>
        </div>

        <!-- Barra de Busca -->
        <div class="flex-1 max-w-lg hidden md:block">
          <input 
            v-model="searchQuery"
            type="text" 
            placeholder="Buscar produtos..." 
            class="w-full bg-slate-900/90 border border-slate-700/80 rounded-full py-2.5 px-5 text-sm text-slate-200 placeholder-slate-500 focus:outline-none focus:border-cyan-400"
          />
        </div>

        <!-- Ações do Cabeçalho -->
        <div class="flex items-center gap-3">
          <!-- Botão Painel de Controle (Admin / Vendedor / Usuário) -->
          <button 
            v-if="currentUser"
            @click="openControlPanel(currentUser.role === 'ADMIN' ? 'sellers' : 'products')"
            class="bg-cyan-500/10 hover:bg-cyan-500/20 border border-cyan-500/30 text-cyan-300 font-bold text-xs py-2 px-3.5 rounded-full transition-all flex items-center gap-1.5 cursor-pointer"
          >
            ⚙️ Painel {{ currentUser.role === 'ADMIN' ? 'Geral (Admin)' : 'da Conta' }}
          </button>

          <!-- Status / Logout -->
          <div v-if="currentUser" class="flex items-center gap-2.5 bg-slate-900 border border-slate-800 rounded-full px-3.5 py-1.5">
            <span class="text-xs font-bold text-slate-200">{{ currentUser.name }}</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-cyan-400 font-mono">{{ currentUser.role }}</span>
            <button @click="logout" class="text-xs text-rose-400 hover:underline">Sair</button>
          </div>

          <button 
            v-else
            @click="isAuthModalOpen = true"
            class="bg-slate-900 hover:bg-slate-800 border border-slate-700 py-2 px-4 rounded-full text-xs font-bold text-slate-200 transition-all cursor-pointer"
          >
            Entrar / Criar Conta
          </button>

          <!-- Carrinho -->
          <button 
            @click="isCartOpen = true"
            class="bg-slate-900 border border-purple-500/30 py-2 px-3.5 rounded-full flex items-center gap-2 cursor-pointer"
          >
            <span class="text-xs text-cyan-400 font-bold">🛒</span>
            <span class="bg-cyan-500 text-slate-950 text-xs font-black rounded-full px-1.5">{{ cartCount }}</span>
          </button>
        </div>
      </div>
    </header>

    <!-- Vitrine Principal -->
    <main class="flex-1 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 w-full">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h2 class="text-2xl font-bold text-white tracking-wide">Vitrine de Produtos</h2>
          <p class="text-sm text-slate-400">Compre de vendedores certificados ou publique seus próprios itens</p>
        </div>
        <button @click="fetchProducts" class="text-xs text-cyan-400 hover:underline flex items-center gap-1 cursor-pointer">
          🔄 Atualizar Catálogo
        </button>
      </div>

      <div v-if="isLoading" class="py-24 text-center">
        <div class="w-10 h-10 border-4 border-cyan-400 border-t-transparent rounded-full animate-spin mx-auto mb-3"></div>
        <p class="text-slate-400 text-xs">Carregando itens...</p>
      </div>

      <div v-else-if="filteredProducts.length === 0" class="py-20 text-center max-w-md mx-auto bg-slate-900/40 rounded-2xl border border-slate-800 p-8">
        <h3 class="text-base font-bold text-white mb-2">Nenhum produto publicado no momento</h3>
        <p class="text-xs text-slate-400 leading-relaxed mb-6">
          Use o painel para cadastrar o primeiro produto no catálogo do Nexus.
        </p>
        <button 
          v-if="currentUser"
          @click="openControlPanel('products')"
          class="bg-gradient-to-r from-purple-600 to-cyan-500 text-white font-bold text-xs py-2.5 px-5 rounded-xl cursor-pointer"
        >
          + Adicionar Primeiro Produto
        </button>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <div 
          v-for="product in filteredProducts" 
          :key="product.id"
          class="rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-purple-500/40 p-5 flex flex-col justify-between transition-all"
        >
          <div>
            <div class="relative w-full h-48 rounded-xl overflow-hidden bg-slate-950 mb-4 flex items-center justify-center">
              <img 
                v-if="product.image_url || product.image" 
                :src="product.image_url || product.image" 
                :alt="product.title || product.name" 
                class="w-full h-full object-cover"
              />
              <span v-else class="text-slate-600 text-xs font-bold uppercase">Sem Imagem</span>
            </div>

            <h3 class="text-base font-bold text-white">{{ product.title || product.name }}</h3>
            <p class="mt-1 text-xs text-slate-400 line-clamp-2">{{ product.description }}</p>
          </div>

          <div class="mt-4 pt-4 border-t border-slate-800 flex items-center justify-between">
            <span class="text-lg font-extrabold text-white">R$ {{ Number(product.price).toFixed(2) }}</span>
            <button 
              @click="addToCart(product)"
              class="bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs py-2 px-3.5 rounded-lg transition-all cursor-pointer"
            >
              Comprar
            </button>
          </div>
        </div>
      </div>
    </main>

    <!-- MODAL DE CONTROLE CENTRAL (ADMIN & PERFIL) -->
    <div 
      v-if="isControlPanelOpen"
      class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
      @click.self="isControlPanelOpen = false"
    >
      <div class="w-full max-w-2xl bg-[#0C1222] border border-slate-800 rounded-2xl p-6 shadow-2xl flex flex-col max-h-[90vh]">
        <!-- Cabeçalho do Painel -->
        <div class="flex justify-between items-center pb-4 border-b border-slate-800">
          <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
              <span>⚙️ Painel de Operações</span>
              <span class="text-xs px-2 py-0.5 rounded-full bg-purple-500/20 text-purple-300 font-mono">{{ currentUser?.role }}</span>
            </h3>
            <p class="text-xs text-slate-400">Gestão integrada: moderação, anúncios, perfil e segurança</p>
          </div>
          <button @click="isControlPanelOpen = false" class="text-slate-500 hover:text-white">✕</button>
        </div>

        <!-- Abas de Navegação -->
        <div class="flex gap-2 border-b border-slate-800/80 pt-3 pb-2 overflow-x-auto text-xs font-semibold">
          <button 
            v-if="currentUser?.role === 'ADMIN'"
            @click="activeTab = 'sellers'"
            :class="activeTab === 'sellers' ? 'bg-purple-600 text-white' : 'text-slate-400 hover:text-white bg-slate-900'"
            class="px-3.5 py-1.5 rounded-lg transition-all cursor-pointer"
          >
            🛡️ Moderação Lojistas
          </button>
          
          <button 
            @click="activeTab = 'products'"
            :class="activeTab === 'products' ? 'bg-cyan-600 text-white' : 'text-slate-400 hover:text-white bg-slate-900'"
            class="px-3.5 py-1.5 rounded-lg transition-all cursor-pointer"
          >
            📦 Anunciar Produto
          </button>

          <button 
            @click="activeTab = 'profile'"
            :class="activeTab === 'profile' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white bg-slate-900'"
            class="px-3.5 py-1.5 rounded-lg transition-all cursor-pointer"
          >
            👤 Meu Perfil & CPF
          </button>

          <button 
            @click="activeTab = 'security'"
            :class="activeTab === 'security' ? 'bg-rose-600 text-white' : 'text-slate-400 hover:text-white bg-slate-900'"
            class="px-3.5 py-1.5 rounded-lg transition-all cursor-pointer"
          >
            🔒 Alterar Senha
          </button>
        </div>

        <!-- Conteúdo das Abas -->
        <div class="pt-4 overflow-y-auto flex-1">

          <!-- ABA 1: MODERAÇÃO ADMIN -->
          <div v-if="activeTab === 'sellers' && currentUser?.role === 'ADMIN'" class="space-y-4">
            <div class="flex justify-between items-center">
              <span class="text-xs font-bold text-slate-300">Solicitações de Vendedores</span>
              <button @click="fetchPendingSellers" class="text-xs text-cyan-400 hover:underline">Atualizar Fila</button>
            </div>

            <div v-if="adminLoading" class="py-10 text-center text-xs text-slate-400">Consultando fila...</div>
            <div v-else-if="pendingSellers.length === 0" class="py-10 text-center text-xs text-slate-500">
              Nenhum lojista pendente de aprovação.
            </div>
            <div v-else class="space-y-2.5">
              <div 
                v-for="seller in pendingSellers" 
                :key="seller.id"
                class="p-3.5 bg-slate-900 border border-slate-800 rounded-xl flex items-center justify-between"
              >
                <div>
                  <p class="text-xs font-bold text-white">{{ seller.name }}</p>
                  <p class="text-[11px] text-slate-400">{{ seller.email }}</p>
                </div>
                <button 
                  @click="approveSeller(seller.id)"
                  class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs py-1.5 px-3 rounded-lg cursor-pointer"
                >
                  Aprovar Conta
                </button>
              </div>
            </div>
          </div>

          <!-- ABA 2: ANUNCIAR PRODUTO -->
          <div v-if="activeTab === 'products'">
            <form @submit.prevent="handleCreateProduct" class="space-y-3">
              <div v-if="prodMsg" class="p-2.5 text-xs rounded-lg font-semibold" :class="prodMsg.includes('sucesso') ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'">
                {{ prodMsg }}
              </div>

              <div>
                <label class="block text-[11px] text-slate-400 mb-1 font-semibold">Nome / Título do Produto</label>
                <input v-model="newProd.title" type="text" required placeholder="Ex: Teclado Mecânico RGB" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-[11px] text-slate-400 mb-1 font-semibold">Preço (R$)</label>
                  <input v-model="newProd.price" type="number" step="0.01" required placeholder="199.90" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
                </div>
                <div>
                  <label class="block text-[11px] text-slate-400 mb-1 font-semibold">Quantidade em Estoque</label>
                  <input v-model="newProd.stock_quantity" type="number" min="1" required class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
                </div>
              </div>

              <div>
                <label class="block text-[11px] text-slate-400 mb-1 font-semibold">URL da Imagem</label>
                <input v-model="newProd.image_url" type="url" placeholder="https://..." class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
              </div>

              <div>
                <label class="block text-[11px] text-slate-400 mb-1 font-semibold">Descrição</label>
                <textarea v-model="newProd.description" rows="2" placeholder="Detalhes técnicos, estado do item, etc." class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white"></textarea>
              </div>

              <button 
                type="submit" 
                :disabled="prodLoading"
                class="w-full bg-gradient-to-r from-cyan-600 to-blue-600 text-white font-bold py-2.5 rounded-lg text-xs uppercase disabled:opacity-50 cursor-pointer"
              >
                {{ prodLoading ? 'Cadastrando...' : 'Publicar Produto no Site' }}
              </button>
            </form>
          </div>

          <!-- ABA 3: PERFIL & CPF -->
          <div v-if="activeTab === 'profile'">
            <form @submit.prevent="handleUpdateProfile" class="space-y-3">
              <div v-if="profileMsg" class="p-2.5 text-xs rounded-lg font-semibold" :class="profileMsg.includes('sucesso') ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'">
                {{ profileMsg }}
              </div>

              <div>
                <label class="block text-[11px] text-slate-400 mb-1 font-semibold">Nome Completo</label>
                <input v-model="profileForm.name" type="text" required class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
              </div>

              <div>
                <label class="block text-[11px] text-slate-400 mb-1 font-semibold">E-mail Cadastrado</label>
                <input v-model="profileForm.email" type="email" required class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
              </div>

              <div>
                <label class="block text-[11px] text-slate-400 mb-1 font-semibold">CPF Vinculado</label>
                <input v-model="profileForm.cpf" type="text" placeholder="000.000.000-00" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
                <span class="text-[10px] text-slate-500">O CPF é usado para liquidação financeira e segurança da conta.</span>
              </div>

              <button 
                type="submit" 
                :disabled="profileLoading"
                class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 rounded-lg text-xs uppercase disabled:opacity-50 cursor-pointer"
              >
                {{ profileLoading ? 'Salvando...' : 'Atualizar Informações' }}
              </button>
            </form>
          </div>

          <!-- ABA 4: ALTERAÇÃO DE SENHA -->
          <div v-if="activeTab === 'security'">
            <form @submit.prevent="handleUpdatePassword" class="space-y-3">
              <div v-if="passMsg" class="p-2.5 text-xs rounded-lg font-semibold" :class="passMsg.includes('sucesso') ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'">
                {{ passMsg }}
              </div>

              <div>
                <label class="block text-[11px] text-slate-400 mb-1 font-semibold">Senha Atual</label>
                <input v-model="passForm.current_password" type="password" required class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
              </div>

              <div>
                <label class="block text-[11px] text-slate-400 mb-1 font-semibold">Nova Senha</label>
                <input v-model="passForm.new_password" type="password" required minlength="6" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
              </div>

              <div>
                <label class="block text-[11px] text-slate-400 mb-1 font-semibold">Confirmar Nova Senha</label>
                <input v-model="passForm.new_password_confirmation" type="password" required minlength="6" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
              </div>

              <button 
                type="submit" 
                :disabled="passLoading"
                class="w-full bg-rose-600 hover:bg-rose-500 text-white font-bold py-2.5 rounded-lg text-xs uppercase disabled:opacity-50 cursor-pointer"
              >
                {{ passLoading ? 'Atualizando...' : 'Definir Nova Senha' }}
              </button>
            </form>
          </div>

        </div>
      </div>
    </div>

    <!-- MODAL DE LOGIN / CADASTRO -->
    <div 
      v-if="isAuthModalOpen"
      class="fixed inset-0 z-50 bg-black/75 backdrop-blur-sm flex items-center justify-center p-4"
      @click.self="isAuthModalOpen = false"
    >
      <div class="w-full max-w-md bg-[#0C1222] border border-slate-800 rounded-2xl p-6 shadow-2xl">
        <div class="flex justify-between items-center pb-3 border-b border-slate-800">
          <h3 class="text-base font-bold text-white">{{ authMode === 'login' ? 'Entrar na Conta' : 'Criar Conta' }}</h3>
          <button @click="isAuthModalOpen = false" class="text-slate-500 hover:text-white">✕</button>
        </div>

        <form @submit.prevent="handleAuth" class="mt-4 space-y-3.5">
          <div v-if="authError" class="p-2.5 bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs rounded-lg">
            {{ authError }}
          </div>

          <div v-if="authMode === 'register'">
            <label class="block text-[11px] text-slate-400 font-semibold mb-1">Seu Nome</label>
            <input v-model="name" type="text" required class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
          </div>

          <div>
            <label class="block text-[11px] text-slate-400 font-semibold mb-1">E-mail</label>
            <input v-model="email" type="email" required class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
          </div>

          <div>
            <label class="block text-[11px] text-slate-400 font-semibold mb-1">Senha</label>
            <input v-model="password" type="password" required class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
          </div>

          <div v-if="authMode === 'register'" class="space-y-3 pt-1">
            <div class="flex items-center gap-2">
              <input id="seller_check" v-model="isSeller" type="checkbox" class="w-4 h-4 rounded text-cyan-500" />
              <label for="seller_check" class="text-xs text-slate-300">Quero vender produtos no Nexus</label>
            </div>

            <div v-if="isSeller">
              <label class="block text-[11px] text-slate-400 font-semibold mb-1">Nome da Loja</label>
              <input v-model="storeName" type="text" placeholder="Ex: Tech Store" class="w-full bg-slate-900 border border-slate-800 rounded-lg p-2.5 text-xs text-white" />
            </div>
          </div>

          <button 
            type="submit" 
            :disabled="authLoading"
            class="w-full bg-gradient-to-r from-purple-600 to-cyan-500 text-white font-bold py-2.5 rounded-lg text-xs uppercase tracking-wider disabled:opacity-50 mt-2 cursor-pointer"
          >
            {{ authLoading ? 'Processando...' : (authMode === 'login' ? 'Entrar' : 'Cadastrar') }}
          </button>
        </form>

        <div class="mt-4 pt-3 border-t border-slate-800 text-center text-xs text-slate-400">
          <button @click="authMode = authMode === 'login' ? 'register' : 'login'; authError = ''" class="text-cyan-400 hover:underline">
            {{ authMode === 'login' ? 'Não tem conta? Cadastre-se' : 'Já possui conta? Faça login' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Carrinho Drawer -->
    <div 
      v-if="isCartOpen" 
      class="fixed inset-0 z-50 bg-black/70 backdrop-blur-sm flex justify-end"
      @click.self="isCartOpen = false"
    >
      <div class="w-full max-w-md bg-[#0C1222] border-l border-slate-800 h-full p-6 flex flex-col justify-between">
        <div>
          <div class="flex justify-between items-center pb-4 border-b border-slate-800">
            <h2 class="text-base font-bold text-white">Carrinho ({{ cartCount }})</h2>
            <button @click="isCartOpen = false" class="text-slate-400 hover:text-white">✕</button>
          </div>
          <div v-if="cart.length === 0" class="py-20 text-center text-slate-500 text-xs">
            Seu carrinho está vazio.
          </div>
          <div v-else class="mt-4 space-y-3 overflow-y-auto max-h-[60vh]">
            <div v-for="item in cart" :key="item.id" class="p-3 bg-slate-900 border border-slate-800 rounded-xl flex justify-between items-center">
              <div>
                <p class="text-xs font-bold text-white">{{ item.title || item.name }}</p>
                <p class="text-xs text-cyan-400">R$ {{ Number(item.price).toFixed(2) }} (x{{ item.quantity }})</p>
              </div>
              <button @click="removeFromCart(item.id)" class="text-xs text-rose-400">Remover</button>
            </div>
          </div>
        </div>
        <div class="pt-4 border-t border-slate-800">
          <div class="flex justify-between items-center mb-3">
            <span class="text-xs text-slate-400">Total:</span>
            <span class="text-xl font-bold text-cyan-400">R$ {{ cartTotal.toFixed(2) }}</span>
          </div>
          <button :disabled="cart.length === 0" class="w-full bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold py-3 rounded-xl text-xs uppercase disabled:opacity-40">
            Finalizar Compra via Escrow
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
