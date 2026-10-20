<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useCartStore } from '../stores/cart'
import { 
  ArrowLeft, 
  ShoppingBag, 
  Truck, 
  ShieldCheck, 
  Package, 
  Tag, 
  Check, 
  AlertCircle,
  Loader2,
  Store
} from 'lucide-vue-next'

const route = useRoute()
const router = useRouter()
const cart = useCartStore()

const product = ref(null)
const selectedImage = ref('')
const isLoading = ref(true)
const isAdded = ref(false)

// Simulação de frete na página do produto
const cepInput = ref('')
const shippingOptions = ref([])
const isCalculatingShipping = ref(false)
const shippingError = ref('')

const discountPercentage = computed(() => {
  if (!product.value?.original_price_cents || !product.value?.price_cents) return 0
  if (product.value.original_price_cents > product.value.price_cents) {
    return Math.round(((product.value.original_price_cents - product.value.price_cents) / product.value.original_price_cents) * 100)
  }
  return 0
})

const productImages = computed(() => {
  if (!product.value) return []
  if (Array.isArray(product.value.images) && product.value.images.length > 0) {
    return product.value.images
  }
  return [product.value.image_url]
})

const loadProduct = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get(`/products/${route.params.id}`)
    product.value = data
    selectedImage.value = (Array.isArray(data.images) && data.images[0]) ? data.images[0] : data.image_url
  } catch (err) {
    console.error('Falha ao carregar produto:', err)
  } finally {
    isLoading.value = false
  }
}

const handleAddToCart = () => {
  if (!product.value) return
  cart.addItem(product.value)
  isAdded.value = true
  setTimeout(() => { isAdded.value = false }, 2000)
}

const handleBuyNow = () => {
  handleAddToCart()
  cart.openCart()
}

const calculateShipping = async () => {
  const cleanCep = cepInput.value.replace(/\D/g, '')
  if (cleanCep.length !== 8) {
    shippingError.value = 'Digite um CEP válido com 8 dígitos.'
    return
  }

  shippingError.value = ''
  isCalculatingShipping.value = true
  shippingOptions.value = []

  try {
    const { data } = await axios.post('/shipping/quote', {
      zip_code: cleanCep,
      items: [{
        weight_grams: product.value.weight_grams || 350,
        length_cm: product.value.length_cm || 20,
        width_cm: product.value.width_cm || 15,
        height_cm: product.value.height_cm || 10,
        quantity: 1
      }]
    })
    shippingOptions.value = data.quotes || data.services || []
  } catch (err) {
    shippingError.value = 'Não foi possível calcular o frete para este CEP.'
  } finally {
    isCalculatingShipping.value = false
  }
}

const formatMoney = (cents) => {
  return ((cents || 0) / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}

onMounted(() => {
  loadProduct()
})
</script>

<template>
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    
    <!-- Botão Voltar -->
    <button 
      @click="router.push('/')"
      class="inline-flex items-center gap-2 text-xs font-semibold text-neutral-400 hover:text-white transition cursor-pointer"
    >
      <ArrowLeft class="w-4 h-4" />
      <span>Voltar para a Vitrine</span>
    </button>

    <!-- Estado de Loading -->
    <div v-if="isLoading" class="text-center py-24 text-xs text-neutral-500">
      <Loader2 class="w-8 h-8 animate-spin mx-auto mb-3 text-indigo-500" />
      Carregando detalhes do produto...
    </div>

    <!-- Produto Não Encontrado -->
    <div v-else-if="!product" class="text-center py-20 bg-neutral-900 border border-neutral-800 rounded-3xl p-8 space-y-3 max-w-md mx-auto">
      <AlertCircle class="w-10 h-10 text-rose-500 mx-auto" />
      <h3 class="text-sm font-bold text-white">Produto não localizado</h3>
      <p class="text-xs text-neutral-400">O produto pode ter sido removido ou não está mais ativo.</p>
    </div>

    <!-- Layout de Detalhes -->
    <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
      
      <!-- Coluna da Esquerda: Galeria de Fotos (Mínimo 3) -->
      <div class="lg:col-span-7 space-y-4">
        <!-- Foto Principal em Alta Resolução -->
        <div class="relative aspect-4/3 rounded-3xl overflow-hidden bg-neutral-900 border border-neutral-800 shadow-2xl">
          <img 
            :src="selectedImage" 
            :alt="product.name" 
            class="w-full h-full object-cover transition duration-300"
          />
          <span 
            v-if="discountPercentage > 0"
            class="absolute top-4 left-4 bg-rose-600 text-white text-xs font-black px-2.5 py-1 rounded-xl uppercase tracking-wider shadow-lg"
          >
            -{{ discountPercentage }}% OFF
          </span>
        </div>

        <!-- Thumbnails da Galeria -->
        <div class="grid grid-cols-4 sm:grid-cols-5 gap-3">
          <button 
            v-for="(img, idx) in productImages" 
            :key="idx"
            type="button"
            @click="selectedImage = img"
            class="aspect-square rounded-2xl overflow-hidden bg-neutral-900 border-2 transition cursor-pointer"
            :class="selectedImage === img ? 'border-indigo-500 ring-2 ring-indigo-500/20 scale-95' : 'border-neutral-800 hover:border-neutral-700 opacity-70 hover:opacity-100'"
          >
            <img :src="img" :alt="product.name" class="w-full h-full object-cover" />
          </button>
        </div>
      </div>

      <!-- Coluna da Direita: Dados de Compra -->
      <div class="lg:col-span-5 bg-neutral-900 border border-neutral-800 rounded-3xl p-6 sm:p-8 space-y-6 shadow-xl">
        
        <!-- Categoria e Título -->
        <div class="space-y-2 border-b border-neutral-800 pb-5">
          <span class="text-[10px] font-bold text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 px-2.5 py-1 rounded-lg uppercase tracking-wider">
            {{ product.category?.name || 'Nexus Store' }}
          </span>
          <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight leading-snug">
            {{ product.name }}
          </h1>
          <p class="text-xs text-neutral-400 flex items-center gap-1.5">
            <Package class="w-3.5 h-3.5 text-emerald-400" />
            <span>Estoque disponível: <strong class="text-white">{{ product.stock_quantity }} unidades</strong></span>
          </p>
        </div>

        <!-- Preços -->
        <div class="space-y-1 bg-neutral-950/60 p-4 rounded-2xl border border-neutral-800/80">
          <div v-if="product.original_price_cents && product.original_price_cents > product.price_cents" class="flex items-center gap-2">
            <span class="text-xs text-neutral-500 line-through font-mono">{{ formatMoney(product.original_price_cents) }}</span>
            <span class="text-[10px] font-bold text-rose-400 bg-rose-500/10 px-1.5 py-0.5 rounded-md">Economize {{ formatMoney(product.original_price_cents - product.price_cents) }}</span>
          </div>

          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-black text-white font-mono">{{ formatMoney(product.price_cents) }}</span>
            <span class="text-xs text-neutral-400">à vista no Pix ou Cartão</span>
          </div>
          <p class="text-[11px] text-neutral-500">ou 3x de {{ formatMoney(Math.round(product.price_cents / 3)) }} sem juros</p>
        </div>

        <!-- Botões de Ação -->
        <div class="space-y-3">
          <button 
            @click="handleBuyNow"
            class="w-full py-3.5 rounded-2xl bg-indigo-600 hover:bg-indigo-500 text-white font-black text-xs transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/25 cursor-pointer"
          >
            <ShoppingBag class="w-4 h-4" />
            <span>Comprar Agora</span>
          </button>

          <button 
            @click="handleAddToCart"
            class="w-full py-3 rounded-2xl bg-neutral-950 hover:bg-neutral-800 border border-neutral-800 text-white font-bold text-xs transition flex items-center justify-center gap-2 cursor-pointer"
          >
            <Check v-if="isAdded" class="w-4 h-4 text-emerald-400" />
            <span v-if="isAdded" class="text-emerald-400">Adicionado ao Carrinho!</span>
            <span v-else>Adicionar ao Carrinho</span>
          </button>
        </div>

        <!-- Calculadora de Frete -->
        <div class="border-t border-neutral-800 pt-5 space-y-3">
          <label class="text-xs font-bold text-neutral-300 flex items-center gap-1.5">
            <Truck class="w-4 h-4 text-indigo-400" />
            <span>Simular Frete e Prazo de Entrega</span>
          </label>

          <div class="flex gap-2">
            <input 
              v-model="cepInput"
              type="text" 
              maxlength="9"
              placeholder="Digite seu CEP" 
              class="w-full bg-neutral-950 border border-neutral-800 focus:border-indigo-500 rounded-xl px-3.5 py-2.5 text-xs text-white font-mono placeholder-neutral-500 focus:outline-hidden"
              @keyup.enter="calculateShipping"
            />
            <button 
              @click="calculateShipping"
              :disabled="isCalculatingShipping"
              class="bg-neutral-800 hover:bg-neutral-700 disabled:opacity-50 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition cursor-pointer shrink-0"
            >
              <Loader2 v-if="isCalculatingShipping" class="w-4 h-4 animate-spin" />
              <span v-else>Calcular</span>
            </button>
          </div>

          <p v-if="shippingError" class="text-[11px] text-rose-400">{{ shippingError }}</p>

          <!-- Resultados do Frete -->
          <div v-if="shippingOptions.length > 0" class="space-y-2 pt-2">
            <div 
              v-for="svc in shippingOptions" 
              :key="svc.name"
              class="flex items-center justify-between p-3 rounded-xl bg-neutral-950 border border-neutral-800 text-xs"
            >
              <div>
                <p class="font-bold text-white">{{ svc.name }}</p>
                <p class="text-[10px] text-neutral-400">Prazo: {{ svc.estimated_days }} dias úteis</p>
              </div>
              <span class="font-bold font-mono text-emerald-400">{{ formatMoney(svc.price_cents) }}</span>
            </div>
          </div>
        </div>

        <!-- Descrição Completa -->
        <div class="border-t border-neutral-800 pt-5 space-y-2">
          <h4 class="text-xs font-bold text-white uppercase tracking-wider">Descrição do Produto</h4>
          <p class="text-xs text-neutral-400 leading-relaxed whitespace-pre-line">
            {{ product.description || 'Produto homologado de alta performance com garantia de procedência.' }}
          </p>
        </div>

      </div>

    </div>

  </div>
</template>
