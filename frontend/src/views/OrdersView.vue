<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import { 
  Package, 
  Truck, 
  CheckCircle2, 
  Clock, 
  AlertCircle, 
  ShoppingBag,
  Calendar,
  Barcode
} from 'lucide-vue-next'

const auth = useAuthStore()
const orders = ref([])
const isLoading = ref(true)
const errorMessage = ref('')

const statusSteps = [
  { key: 'PAID', label: 'Pagamento Aprovado', desc: 'Pedido confirmado no sistema' },
  { key: 'IN_PREPARATION', label: 'Em Separação', desc: 'Empacotamento e nota fiscal' },
  { key: 'SHIPPED', label: 'Despachado', desc: 'Em trânsito para seu endereço' },
  { key: 'DELIVERED', label: 'Entregue', desc: 'Pacote recebido pelo destinatário' }
]

const getStepIndex = (status) => {
  switch (status) {
    case 'PAID': return 0
    case 'IN_PREPARATION': return 1
    case 'SHIPPED': return 2
    case 'DELIVERED': return 3
    default: return 0
  }
}

const formatMoney = (cents) => {
  return ((cents || 0) / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}

const formatDate = (dateStr) => {
  if (!dateStr) return 'A definir'
  return new Date(dateStr).toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const calculateDeliveryDate = (order) => {
  const baseDate = new Date(order.created_at)
  const days = order.estimated_delivery_days || 3
  baseDate.setDate(baseDate.getDate() + days)
  return baseDate.toLocaleDateString('pt-BR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric'
  })
}

const loadOrders = async () => {
  isLoading.value = true
  errorMessage.value = ''

  if (!auth.user) {
    errorMessage.value = 'Você precisa estar conectado para visualizar seus pedidos.'
    isLoading.value = false
    return
  }

  try {
    const { data } = await axios.get('/customer/orders', { withCredentials: true })
    orders.value = data
  } catch (err) {
    errorMessage.value = err.response?.data?.message || err.response?.data?.error || 'Não foi possível carregar seus pedidos.'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadOrders()
})
</script>

<template>
  <div class="max-w-4xl mx-auto px-4 py-8 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-neutral-800 pb-5">
      <div class="space-y-1">
        <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2.5">
          <Package class="w-6 h-6 text-indigo-400" />
          <span>Meus Pedidos</span>
        </h1>
        <p class="text-xs text-neutral-400">Acompanhe a separação, envio e entrega das suas compras em tempo real</p>
      </div>

      <button 
        @click="loadOrders" 
        class="text-xs bg-neutral-900 border border-neutral-800 hover:border-neutral-700 text-neutral-300 px-3 py-1.5 rounded-xl transition"
      >
        Atualizar Status
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="isLoading" class="text-center py-16 text-neutral-500 text-xs">
      Carregando histórico de pedidos...
    </div>

    <!-- Erro -->
    <div v-else-if="errorMessage" class="p-4 bg-rose-950/40 border border-rose-800/60 rounded-2xl flex items-center justify-between text-rose-300 text-xs">
      <div class="flex items-center gap-2.5">
        <AlertCircle class="w-4 h-4 shrink-0 text-rose-400" />
        <span>{{ errorMessage }}</span>
      </div>
      <router-link to="/login" class="bg-rose-900/60 hover:bg-rose-800 text-white px-3 py-1 rounded-lg text-xs font-bold transition">
        Fazer Login
      </router-link>
    </div>

    <!-- Vazio -->
    <div v-else-if="orders.length === 0" class="text-center py-16 bg-neutral-900/50 border border-neutral-800 rounded-3xl p-8 space-y-3">
      <ShoppingBag class="w-10 h-10 text-neutral-600 mx-auto stroke-1" />
      <h3 class="text-sm font-bold text-white">Nenhum pedido realizado ainda</h3>
      <p class="text-xs text-neutral-400">Navegue pela loja e adicione produtos ao carrinho para começar.</p>
      <router-link to="/" class="inline-block mt-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs px-4 py-2 rounded-xl transition">
        Explorar Vitrine
      </router-link>
    </div>

    <!-- Lista de Pedidos -->
    <div v-else class="space-y-6">
      <div 
        v-for="order in orders" 
        :key="order.id"
        class="bg-neutral-900 border border-neutral-800 rounded-3xl p-6 shadow-xl space-y-6"
      >
        <!-- Topo do Card -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-neutral-800 pb-4">
          <div class="space-y-0.5">
            <div class="flex items-center gap-2">
              <span class="text-xs text-neutral-400">Pedido</span>
              <span class="text-xs font-mono font-bold text-white">#{{ order.id }}</span>
            </div>
            <p class="text-[11px] text-neutral-500 flex items-center gap-1">
              <Calendar class="w-3 h-3" />
              <span>Realizado em {{ formatDate(order.created_at) }}</span>
            </p>
          </div>

          <div class="text-right">
            <span class="text-[11px] text-neutral-400">Total Pago:</span>
            <p class="text-sm font-black text-emerald-400">{{ formatMoney(order.total_cents) }}</p>
          </div>
        </div>

        <!-- Linha do Tempo (Stepper) -->
        <div class="relative pt-2 pb-4">
          <div class="grid grid-cols-4 gap-2 text-center relative z-10">
            <div 
              v-for="(step, idx) in statusSteps" 
              :key="step.key"
              class="flex flex-col items-center space-y-2"
            >
              <div 
                class="w-9 h-9 rounded-full flex items-center justify-center transition border text-xs"
                :class="idx <= getStepIndex(order.status) 
                  ? 'bg-indigo-600 border-indigo-500 text-white shadow-lg shadow-indigo-600/30' 
                  : 'bg-neutral-950 border-neutral-800 text-neutral-500'"
              >
                <CheckCircle2 v-if="idx < getStepIndex(order.status)" class="w-4 h-4" />
                <Clock v-else-if="idx === getStepIndex(order.status)" class="w-4 h-4 animate-pulse" />
                <span v-else class="text-[10px] font-mono font-bold">{{ idx + 1 }}</span>
              </div>

              <div>
                <p 
                  class="text-xs font-bold leading-tight"
                  :class="idx <= getStepIndex(order.status) ? 'text-white' : 'text-neutral-500'"
                >
                  {{ step.label }}
                </p>
                <p class="text-[10px] text-neutral-500 hidden sm:block mt-0.5">{{ step.desc }}</p>
              </div>
            </div>
          </div>

          <div class="absolute top-[22px] left-[12%] right-[12%] h-0.5 bg-neutral-800 z-0">
            <div 
              class="h-full bg-indigo-600 transition-all duration-500"
              :style="{ width: (getStepIndex(order.status) / 3) * 100 + '%' }"
            />
          </div>
        </div>

        <!-- Previsão & Rastreio -->
        <div class="bg-neutral-950 border border-neutral-800/80 rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 text-xs">
          <div class="space-y-1">
            <span class="text-neutral-400 flex items-center gap-1.5 text-[11px]">
              <Truck class="w-3.5 h-3.5 text-indigo-400" />
              <span>Modalidade: <strong>{{ order.shipping_service }}</strong></span>
            </span>
            <p class="text-neutral-300">
              Previsão de Entrega: <strong class="text-white">{{ calculateDeliveryDate(order) }}</strong>
            </p>
          </div>

          <div v-if="order.tracking_code" class="bg-neutral-900 border border-neutral-800 rounded-xl px-3 py-2 flex items-center gap-2">
            <Barcode class="w-4 h-4 text-indigo-400" />
            <div>
              <p class="text-[10px] text-neutral-400">Código de Rastreio</p>
              <p class="text-xs font-mono font-bold text-white">{{ order.tracking_code }}</p>
            </div>
          </div>
          <div v-else class="text-neutral-500 text-[11px] italic">
            O código de rastreamento será gerado após o despacho.
          </div>
        </div>

        <!-- Itens -->
        <div class="space-y-2 border-t border-neutral-800/80 pt-4">
          <h4 class="text-xs font-semibold text-neutral-400">Itens inclusos:</h4>
          <div class="divide-y divide-neutral-800/60">
            <div 
              v-for="item in order.items" 
              :key="item.id"
              class="py-2.5 flex items-center justify-between text-xs"
            >
              <div class="flex items-center gap-3">
                <img 
                  :src="item.product?.image_url || 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=100'" 
                  :alt="item.product?.title" 
                  class="w-10 h-10 rounded-lg object-cover bg-neutral-800 shrink-0"
                />
                <div>
                  <p class="font-semibold text-white">{{ item.product?.title || 'Produto Nexus' }}</p>
                  <p class="text-[10px] text-neutral-400">Quantidade: {{ item.quantity }} un.</p>
                </div>
              </div>
              <span class="font-bold text-neutral-300 font-mono">
                {{ formatMoney(item.unit_price_cents * item.quantity) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
