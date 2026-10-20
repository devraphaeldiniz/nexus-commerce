<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { 
  Store, 
  Wallet, 
  Lock, 
  CheckCircle2, 
  Package, 
  Truck, 
  Send, 
  AlertCircle, 
  TrendingUp, 
  Barcode,
  Plus,
  Pencil,
  Trash2,
  Tag,
  Layers,
  X,
  Loader2,
  Percent,
  Image as ImageIcon
} from 'lucide-vue-next'

const router = useRouter()
const auth = useAuthStore()

const activeTab = ref('products')
const metrics = ref(null)
const orders = ref([])
const myProducts = ref([])
const categories = ref([])
const isLoading = ref(true)
const errorMessage = ref('')
const trackingInputs = ref({})
const dispatchLoading = ref({})

// Modal de Criação / Edição
const isProductModalOpen = ref(false)
const isEditing = ref(false)
const editingProductId = ref(null)
const isSubmittingProduct = ref(false)
const formError = ref('')

const productForm = ref({
  name: '',
  description: '',
  category_id: '',
  price_reais: '',
  original_price_reais: '',
  stock_quantity: 10,
  images: ['', '', ''],
  weight_grams: 500,
  length_cm: 20,
  width_cm: 15,
  height_cm: 10,
})

// Presets com 3 imagens reais cada para teste rápido
const galleryPacks = [
  {
    name: 'Pack Teclado RGB',
    images: [
      'https://images.unsplash.com/photo-1587829741301-dc798b83add3',
      'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef',
      'https://images.unsplash.com/photo-1595225476474-87563907a212'
    ]
  },
  {
    name: 'Pack Headset Gamer',
    images: [
      'https://images.unsplash.com/photo-1505740420928-5e560c06d30e',
      'https://images.unsplash.com/photo-1484704849700-f032a568e944',
      'https://images.unsplash.com/photo-1546435770-a3e426bf472b'
    ]
  },
  {
    name: 'Pack Smartwatch',
    images: [
      'https://images.unsplash.com/photo-1523275335684-37898b6baf30',
      'https://images.unsplash.com/photo-1508685096489-7aacd43bd3b1',
      'https://images.unsplash.com/photo-1579586337278-3befd40fd17a'
    ]
  }
]

const discountPercentage = computed(() => {
  const current = parseFloat(String(productForm.value.price_reais).replace(',', '.'))
  const original = parseFloat(String(productForm.value.original_price_reais).replace(',', '.'))
  if (original && current && original > current) {
    return Math.round(((original - current) / original) * 100)
  }
  return 0
})

const getToken = () => null

const loadDashboard = async () => {
  const currentToken = getToken()

  if (!currentToken) {
    errorMessage.value = 'Você precisa estar logado como vendedor para acessar este painel.'
    isLoading.value = false
    return
  }

  isLoading.value = true
  errorMessage.value = ''
  try {
    const config = {
      headers: { Authorization: `Bearer ${currentToken}` },
    }

    const [metricsRes, ordersRes, productsRes, categoriesRes] = await Promise.all([
      axios.get('/seller/metrics', config),
      axios.get('/seller/orders', config),
      axios.get('/seller/products', config),
      axios.get('/categories')
    ])

    metrics.value = metricsRes.data
    orders.value = ordersRes.data
    myProducts.value = productsRes.data
    categories.value = categoriesRes.data

    if (categoriesRes.data.length > 0 && !productForm.value.category_id) {
      productForm.value.category_id = categoriesRes.data[0].id
    }

    ordersRes.data.forEach(o => {
      trackingInputs.value[o.id] = o.tracking_code || ''
    })
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Falha ao carregar dados do painel.'
  } finally {
    isLoading.value = false
  }
}

const applyGalleryPack = (pack) => {
  productForm.value.images = [...pack.images]
}

const openCreateModal = () => {
  isEditing.value = false
  editingProductId.value = null
  formError.value = ''
  productForm.value = {
    name: '',
    description: '',
    category_id: categories.value[0]?.id || '',
    price_reais: '',
    original_price_reais: '',
    stock_quantity: 10,
    images: [
      galleryPacks[0].images[0],
      galleryPacks[0].images[1],
      galleryPacks[0].images[2]
    ],
    weight_grams: 500,
    length_cm: 20,
    width_cm: 15,
    height_cm: 10,
  }
  isProductModalOpen.value = true
}

const openEditModal = (product) => {
  isEditing.value = true
  editingProductId.value = product.id
  formError.value = ''
  
  let imgs = Array.isArray(product.images) && product.images.length >= 3 
    ? [...product.images] 
    : [product.image_url, product.image_url, product.image_url]

  while (imgs.length < 3) {
    imgs.push(imgs[0] || 'https://images.unsplash.com/photo-1523275335684-37898b6baf30')
  }

  productForm.value = {
    name: product.name,
    description: product.description || '',
    category_id: product.category_id,
    price_reais: ((product.price_cents || 0) / 100).toFixed(2).replace('.', ','),
    original_price_reais: product.original_price_cents ? ((product.original_price_cents) / 100).toFixed(2).replace('.', ',') : '',
    stock_quantity: product.stock_quantity,
    images: imgs,
    weight_grams: product.weight_grams || 350,
    length_cm: product.length_cm || 30,
    width_cm: product.width_cm || 20,
    height_cm: product.height_cm || 15,
  }
  isProductModalOpen.value = true
}

const handleSaveProduct = async () => {
  formError.value = ''
  isSubmittingProduct.value = true

  try {
    const currentToken = getToken()
    const config = {
      headers: { Authorization: `Bearer ${currentToken}` },
    }

    const priceCents = Math.round(parseFloat(String(productForm.value.price_reais).replace(',', '.')) * 100)
    let originalPriceCents = null

    if (productForm.value.original_price_reais) {
      const parsedOriginal = Math.round(parseFloat(String(productForm.value.original_price_reais).replace(',', '.')) * 100)
      if (!isNaN(parsedOriginal) && parsedOriginal > priceCents) {
        originalPriceCents = parsedOriginal
      }
    }

    if (isNaN(priceCents) || priceCents < 100) {
      formError.value = 'Informe um preço válido maior que R$ 1,00.'
      isSubmittingProduct.value = false
      return
    }

    const validImages = productForm.value.images.filter(img => img && img.trim().length > 0)
    if (validImages.length < 3) {
      formError.value = 'É obrigatório informar no mínimo 3 fotos válidas para o produto.'
      isSubmittingProduct.value = false
      return
    }

    const payload = {
      name: productForm.value.name,
      description: productForm.value.description,
      category_id: productForm.value.category_id,
      price_cents: priceCents,
      original_price_cents: originalPriceCents,
      stock_quantity: parseInt(productForm.value.stock_quantity) || 0,
      images: validImages,
      weight_grams: parseInt(productForm.value.weight_grams) || 350,
      length_cm: parseInt(productForm.value.length_cm) || 20,
      width_cm: parseInt(productForm.value.width_cm) || 15,
      height_cm: parseInt(productForm.value.height_cm) || 10,
    }

    if (isEditing.value) {
      const { data } = await axios.put(`/seller/products/${editingProductId.value}`, payload, config)
      const index = myProducts.value.findIndex(p => p.id === editingProductId.value)
      if (index !== -1) myProducts.value[index] = data.product
    } else {
      const { data } = await axios.post('/seller/products', payload, config)
      myProducts.value.unshift(data.product)
    }

    isProductModalOpen.value = false
  } catch (err) {
    formError.value = err.response?.data?.message || 'Erro ao processar dados do produto.'
  } finally {
    isSubmittingProduct.value = false
  }
}

const handleDeleteProduct = async (productId) => {
  if (!confirm('Deseja realmente remover este produto da loja?')) return

  try {
    const currentToken = getToken()
    const config = {
      headers: { Authorization: `Bearer ${currentToken}` },
    }

    await axios.delete(`/seller/products/${productId}`, config)
    myProducts.value = myProducts.value.filter(p => p.id !== productId)
  } catch (err) {
    alert(err.response?.data?.message || 'Falha ao remover produto.')
  }
}

const handleDispatch = async (orderId) => {
  const code = trackingInputs.value[orderId]
  if (!code || code.trim().length < 6) {
    alert('Digite um código de rastreio válido (ex: BR123456789BR)')
    return
  }

  const currentToken = getToken()
  dispatchLoading.value[orderId] = true
  try {
    const config = {
      headers: { Authorization: `Bearer ${currentToken}` },
    }
    const { data } = await axios.post(`/seller/orders/${orderId}/dispatch`, {
      tracking_code: code
    }, config)

    const order = orders.value.find(o => o.id === orderId)
    if (order) {
      order.status = data.status
      order.tracking_code = data.tracking_code
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Erro ao despachar pedido.')
  } finally {
    dispatchLoading.value[orderId] = false
  }
}

const formatMoney = (cents) => {
  return ((cents || 0) / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  return new Date(dateStr).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

onMounted(() => {
  loadDashboard()
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-neutral-800 pb-6">
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <Store class="w-6 h-6 text-indigo-400" />
          <h1 class="text-2xl font-black text-white tracking-tight">
            {{ metrics?.store_name || 'Painel do Vendedor' }}
          </h1>
          <span class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase">
            {{ metrics?.kyc_status || 'Verificado' }}
          </span>
        </div>
        <p class="text-xs text-neutral-400">
          CNPJ: {{ metrics?.document_number || '99.888.777/0001-66' }} • Gerencie estoque, preços e fotos dos produtos
        </p>
      </div>

      <div class="flex items-center gap-3">
        <button 
          @click="openCreateModal"
          class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition flex items-center gap-1.5 shadow-lg shadow-indigo-600/20 cursor-pointer"
        >
          <Plus class="w-4 h-4" />
          <span>Novo Anúncio (3 Fotos)</span>
        </button>

        <button 
          @click="loadDashboard"
          class="text-xs bg-neutral-900 border border-neutral-800 hover:border-neutral-700 text-neutral-300 px-3.5 py-2 rounded-xl transition cursor-pointer"
        >
          Atualizar
        </button>
      </div>
    </div>

    <!-- Mensagem de Erro -->
    <div v-if="errorMessage" class="p-6 bg-neutral-900 border border-neutral-800 rounded-3xl text-center space-y-4 max-w-md mx-auto my-12">
      <div class="w-12 h-12 rounded-2xl bg-rose-500/10 text-rose-400 border border-rose-500/20 flex items-center justify-center mx-auto">
        <AlertCircle class="w-6 h-6" />
      </div>
      <div class="space-y-1">
        <h3 class="text-sm font-bold text-white">Atenção</h3>
        <p class="text-xs text-neutral-400">{{ errorMessage }}</p>
      </div>
      <router-link to="/" class="inline-flex items-center justify-center gap-2 bg-indigo-600 text-white font-bold text-xs px-5 py-2.5 rounded-xl transition">
        Voltar à Loja
      </router-link>
    </div>

    <!-- Dashboard Completo -->
    <div v-else class="space-y-8">
      
      <!-- Cards Financeiros -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-neutral-900 border border-neutral-800 rounded-3xl p-5 space-y-2">
          <div class="flex items-center justify-between text-neutral-400">
            <span class="text-xs font-semibold">Total em Vendas</span>
            <TrendingUp class="w-4 h-4 text-indigo-400" />
          </div>
          <p class="text-xl font-black text-white font-mono">{{ formatMoney(metrics?.total_sales_cents) }}</p>
          <span class="text-[10px] text-neutral-500">{{ metrics?.total_orders_count || 0 }} pedidos</span>
        </div>

        <div class="bg-neutral-900 border border-neutral-800 rounded-3xl p-5 space-y-2">
          <div class="flex items-center justify-between text-amber-400">
            <span class="text-xs font-semibold">Em Custódia</span>
            <Lock class="w-4 h-4" />
          </div>
          <p class="text-xl font-black text-amber-300 font-mono">{{ formatMoney(metrics?.balance_escrow_cents) }}</p>
          <span class="text-[10px] text-neutral-500">87% líquido retido até a entrega</span>
        </div>

        <div class="bg-neutral-900 border border-neutral-800 rounded-3xl p-5 space-y-2">
          <div class="flex items-center justify-between text-emerald-400">
            <span class="text-xs font-semibold">Saldo Disponível</span>
            <Wallet class="w-4 h-4" />
          </div>
          <p class="text-xl font-black text-emerald-400 font-mono">{{ formatMoney(metrics?.balance_available_cents) }}</p>
          <span class="text-[10px] text-neutral-500">Liberado para saque</span>
        </div>

        <div class="bg-neutral-900 border border-neutral-800 rounded-3xl p-5 space-y-2">
          <div class="flex items-center justify-between text-neutral-400">
            <span class="text-xs font-semibold">Produtos Anunciados</span>
            <Layers class="w-4 h-4 text-neutral-400" />
          </div>
          <p class="text-xl font-black text-neutral-200 font-mono">{{ myProducts.length }}</p>
          <span class="text-[10px] text-neutral-500">Itens ativos com galeria</span>
        </div>
      </div>

      <!-- Abas -->
      <div class="flex gap-2 border-b border-neutral-800 pb-2">
        <button 
          @click="activeTab = 'products'"
          class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer"
          :class="activeTab === 'products' ? 'bg-indigo-600 text-white' : 'text-neutral-400 hover:text-white hover:bg-neutral-900'"
        >
          <Package class="w-4 h-4" />
          <span>Meus Produtos ({{ myProducts.length }})</span>
        </button>

        <button 
          @click="activeTab = 'orders'"
          class="px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer"
          :class="activeTab === 'orders' ? 'bg-indigo-600 text-white' : 'text-neutral-400 hover:text-white hover:bg-neutral-900'"
        >
          <Truck class="w-4 h-4" />
          <span>Fila de Envios ({{ orders.length }})</span>
        </button>
      </div>

      <!-- ABA MEUS PRODUTOS -->
      <div v-if="activeTab === 'products'" class="space-y-4">
        <div v-if="isLoading" class="text-center py-16 text-xs text-neutral-500">
          Carregando catálogo...
        </div>

        <div v-else-if="myProducts.length === 0" class="text-center py-16 bg-neutral-900/40 border border-neutral-800 rounded-3xl p-8 space-y-3">
          <Package class="w-12 h-12 text-neutral-600 mx-auto stroke-1" />
          <h4 class="text-sm font-bold text-white">Nenhum produto anunciado</h4>
          <p class="text-xs text-neutral-400 max-w-sm mx-auto">Cadastre anúncios com 3 fotos para que apareçam na loja.</p>
          <button @click="openCreateModal" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-2 rounded-xl transition">
            Cadastrar Primeiro Produto
          </button>
        </div>

        <!-- Grade de Produtos -->
        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div 
            v-for="product in myProducts" 
            :key="product.id"
            class="bg-neutral-900 border border-neutral-800 rounded-2xl p-4 flex flex-col justify-between space-y-3 hover:border-neutral-700 transition"
          >
            <div class="flex gap-3">
              <img 
                :src="(product.images && product.images[0]) || product.image_url" 
                :alt="product.name" 
                class="w-16 h-16 rounded-xl object-cover bg-neutral-950 border border-neutral-800 shrink-0" 
              />
              <div class="space-y-1 min-w-0 flex-1">
                <div class="flex items-center justify-between gap-1">
                  <span class="text-[9px] font-bold text-indigo-400 bg-indigo-500/10 px-2 py-0.5 rounded-md uppercase">
                    {{ product.category?.name || 'Geral' }}
                  </span>
                  <span 
                    v-if="product.original_price_cents && product.original_price_cents > product.price_cents"
                    class="text-[9px] font-bold text-rose-400 bg-rose-500/10 border border-rose-500/20 px-1.5 py-0.2 rounded-md"
                  >
                    -{{ Math.round(((product.original_price_cents - product.price_cents) / product.original_price_cents) * 100) }}% OFF
                  </span>
                </div>

                <h3 class="text-xs font-bold text-white truncate" :title="product.name">{{ product.name }}</h3>

                <div class="flex items-baseline gap-1.5">
                  <span class="text-sm font-black text-emerald-400 font-mono">{{ formatMoney(product.price_cents) }}</span>
                  <span 
                    v-if="product.original_price_cents && product.original_price_cents > product.price_cents"
                    class="text-[10px] text-neutral-500 line-through font-mono"
                  >
                    {{ formatMoney(product.original_price_cents) }}
                  </span>
                </div>
              </div>
            </div>

            <!-- Rodapé do Card -->
            <div class="flex items-center justify-between pt-2.5 border-t border-neutral-800/80 text-[11px] text-neutral-400">
              <span>Estoque: <strong class="text-white">{{ product.stock_quantity }} un.</strong></span>

              <div class="flex items-center gap-1">
                <button 
                  @click="openEditModal(product)"
                  title="Editar produto"
                  class="p-1.5 text-neutral-400 hover:text-indigo-400 hover:bg-neutral-800 rounded-lg transition cursor-pointer"
                >
                  <Pencil class="w-3.5 h-3.5" />
                </button>

                <button 
                  @click="handleDeleteProduct(product.id)"
                  title="Excluir produto"
                  class="p-1.5 text-neutral-400 hover:text-rose-400 hover:bg-neutral-800 rounded-lg transition cursor-pointer"
                >
                  <Trash2 class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ABA FILA DE ENVIOS -->
      <div v-if="activeTab === 'orders'" class="space-y-4">
        <div v-if="orders.length === 0" class="text-center py-16 bg-neutral-900/40 border border-neutral-800 rounded-3xl p-8 space-y-2">
          <Package class="w-10 h-10 text-neutral-600 mx-auto stroke-1" />
          <h4 class="text-xs font-bold text-white">Nenhum pedido aguardando despacho</h4>
        </div>

        <div v-else class="space-y-4">
          <div 
            v-for="order in orders" 
            :key="order.id"
            class="bg-neutral-900 border border-neutral-800 rounded-3xl p-6 space-y-4 shadow-lg"
          >
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-800 pb-4">
              <div>
                <span class="text-xs font-mono font-bold text-white">#{{ order.id }}</span>
                <span class="ml-2 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                  {{ order.status }}
                </span>
                <p class="text-[11px] text-neutral-500">{{ formatDate(order.created_at) }} • CEP: {{ order.shipping_zip_code }}</p>
              </div>
              <p class="text-sm font-black text-emerald-400 font-mono">{{ formatMoney(order.seller_net_cents) }}</p>
            </div>

            <div class="bg-neutral-950 border border-neutral-800 rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 text-xs">
              <div>
                <p class="font-bold text-white">Envio: {{ order.shipping_service }}</p>
                <p class="text-[11px] text-neutral-400">{{ order.shipping_address }}</p>
              </div>

              <div class="flex items-center gap-2 w-full sm:w-auto">
                <input 
                  v-model="trackingInputs[order.id]" 
                  type="text" 
                  placeholder="BR123456789BR"
                  class="bg-neutral-900 border border-neutral-700 rounded-xl px-3 py-2 text-xs text-white font-mono uppercase"
                />
                <button 
                  @click="handleDispatch(order.id)"
                  class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-4 py-2 rounded-xl text-xs"
                >
                  Despachar
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- MODAL DE CADASTRO / EDIÇÃO COM 3 FOTOS -->
    <div v-if="isProductModalOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
      <div class="fixed inset-0 bg-black/80 backdrop-blur-xs" @click="isProductModalOpen = false" />

      <div class="relative bg-neutral-900 border border-neutral-800 rounded-3xl w-full max-w-xl p-6 sm:p-8 shadow-2xl text-white space-y-6 z-10">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-4">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-400 flex items-center justify-center">
              <Pencil v-if="isEditing" class="w-5 h-5" />
              <Plus v-else class="w-5 h-5" />
            </div>
            <div>
              <h3 class="text-sm font-black">{{ isEditing ? 'Editar Anúncio e Galeria' : 'Novo Anúncio no Marketplace' }}</h3>
              <p class="text-[11px] text-neutral-400">Mínimo obrigatório de 3 fotos para o carrossel</p>
            </div>
          </div>

          <button @click="isProductModalOpen = false" class="p-2 text-neutral-400 hover:text-white rounded-xl transition cursor-pointer">
            <X class="w-4 h-4" />
          </button>
        </div>

        <p v-if="formError" class="p-3 bg-rose-950/40 border border-rose-900 rounded-xl text-xs text-rose-300">
          {{ formError }}
        </p>

        <form @submit.prevent="handleSaveProduct" class="space-y-4">
          <!-- Título -->
          <div class="space-y-1">
            <label class="text-[11px] font-semibold text-neutral-300">Título do Produto *</label>
            <input 
              v-model="productForm.name" 
              type="text" 
              placeholder="Ex: Teclado Mecânico RGB Switch Blue" 
              required
              class="w-full bg-neutral-950 border border-neutral-800 focus:border-indigo-500 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-hidden"
            />
          </div>

          <!-- Categoria -->
          <div class="space-y-1">
            <label class="text-[11px] font-semibold text-neutral-300">Categoria *</label>
            <select 
              v-model="productForm.category_id"
              required
              class="w-full bg-neutral-950 border border-neutral-800 focus:border-indigo-500 rounded-xl px-3 py-2 text-xs text-white focus:outline-hidden"
            >
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
            </select>
          </div>

          <!-- Preços -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-3 bg-neutral-950 border border-neutral-800 rounded-2xl">
            <div class="space-y-1">
              <label class="text-[11px] font-semibold text-emerald-400 flex items-center justify-between">
                <span>Preço de Venda (R$) *</span>
                <span v-if="discountPercentage > 0" class="text-[10px] text-rose-400 font-bold">{{ discountPercentage }}% OFF</span>
              </label>
              <input 
                v-model="productForm.price_reais" 
                type="text" 
                placeholder="Ex: 199,90" 
                required
                class="w-full bg-neutral-900 border border-neutral-700 rounded-xl px-3.5 py-2 text-xs text-white font-mono focus:outline-hidden"
              />
            </div>

            <div class="space-y-1">
              <label class="text-[11px] font-semibold text-neutral-400">Preço Original "De:" (Opcional)</label>
              <input 
                v-model="productForm.original_price_reais" 
                type="text" 
                placeholder="Ex: 249,90" 
                class="w-full bg-neutral-900 border border-neutral-700 rounded-xl px-3.5 py-2 text-xs text-white font-mono focus:outline-hidden"
              />
            </div>
          </div>

          <!-- Galeria: 3 Fotos Obrigatórias -->
          <div class="space-y-2 p-3 bg-neutral-950 border border-neutral-800 rounded-2xl">
            <div class="flex items-center justify-between">
              <label class="text-[11px] font-semibold text-neutral-300 flex items-center gap-1.5">
                <ImageIcon class="w-3.5 h-3.5 text-indigo-400" />
                <span>Fotos do Produto (Mínimo 3 Fotos) *</span>
              </label>
            </div>

            <!-- Presets de Teste em 1 clique -->
            <div class="flex items-center gap-1.5 flex-wrap pb-1">
              <span class="text-[10px] text-neutral-500">Packs de Fotos Prontos:</span>
              <button 
                v-for="pack in galleryPacks" 
                :key="pack.name"
                type="button"
                @click="applyGalleryPack(pack)"
                class="text-[10px] bg-neutral-800 hover:bg-neutral-700 text-neutral-300 px-2 py-0.5 rounded-md transition cursor-pointer"
              >
                {{ pack.name }}
              </button>
            </div>

            <div class="space-y-2">
              <div v-for="n in 3" :key="n" class="flex items-center gap-2">
                <span class="text-[10px] font-mono text-neutral-500 w-14 shrink-0">Foto {{ n }}:</span>
                <input 
                  v-model="productForm.images[n - 1]" 
                  type="url" 
                  :placeholder="`URL da Foto ${n}...`" 
                  required
                  class="flex-1 bg-neutral-900 border border-neutral-700 focus:border-indigo-500 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-hidden"
                />
              </div>
            </div>
          </div>

          <!-- Estoque e Peso -->
          <div class="grid grid-cols-2 gap-3">
            <div class="space-y-1">
              <label class="text-[11px] font-semibold text-neutral-300">Estoque *</label>
              <input 
                v-model="productForm.stock_quantity" 
                type="number" 
                min="0"
                required
                class="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2 text-xs text-white font-mono focus:outline-hidden"
              />
            </div>

            <div class="space-y-1">
              <label class="text-[11px] font-semibold text-neutral-300">Peso (g) *</label>
              <input 
                v-model="productForm.weight_grams" 
                type="number" 
                min="10"
                required
                class="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2 text-xs text-white font-mono focus:outline-hidden"
              />
            </div>
          </div>

          <!-- Descrição -->
          <div class="space-y-1">
            <label class="text-[11px] font-semibold text-neutral-300">Descrição</label>
            <textarea 
              v-model="productForm.description" 
              rows="2"
              class="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3.5 py-2 text-xs text-white focus:outline-hidden resize-none"
            />
          </div>

          <button 
            type="submit" 
            :disabled="isSubmittingProduct"
            class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20 disabled:opacity-50 cursor-pointer"
          >
            <Loader2 v-if="isSubmittingProduct" class="w-4 h-4 animate-spin" />
            <span v-else>{{ isEditing ? 'Salvar Produto & Fotos' : 'Publicar com 3 Fotos' }}</span>
          </button>
        </form>
      </div>
    </div>

  </div>
</template>
