<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { 
  Boxes, Barcode, Scale, Package, Truck, CheckCircle2, RefreshCw, Eye, EyeOff 
} from 'lucide-vue-next'

const orders = ref([])
const mode = ref('OPERATIONAL_FLOOR_VIEW')
const isLoading = ref(true)
const filterStatus = ref('ALL')
const actionLoading = ref(null)

const loadQueue = async () => {
  isLoading.value = true
  try {
    const { data } = await axios.get('http://localhost:8000/api/logistics/queue', {
      params: { status: filterStatus.value }
    })
    orders.value = data.orders
    mode.value = data.mode
  } catch (err) {
    console.error('Falha ao carregar esteira logística:', err)
  } finally {
    isLoading.value = false
  }
}

const markInPreparation = async (orderId) => {
  actionLoading.value = orderId
  try {
    await axios.post(`http://localhost:8000/api/logistics/orders/${orderId}/prepare`)
    await loadQueue()
  } catch (err) {
    alert(err.response?.data?.message || 'Falha ao mover para separação.')
  } finally {
    actionLoading.value = null
  }
}

const shipOrder = async (orderId) => {
  actionLoading.value = orderId
  try {
    const { data } = await axios.post(`http://localhost:8000/api/orders/${orderId}/ship`)
    alert(`Etiqueta emitida com sucesso! Rastreio: ${data.tracking_code}`)
    await loadQueue()
  } catch (err) {
    alert(err.response?.data?.message || 'Falha ao despachar.')
  } finally {
    actionLoading.value = null
  }
}

const deliverOrder = async (orderId) => {
  actionLoading.value = orderId
  try {
    await axios.post(`http://localhost:8000/api/orders/${orderId}/deliver`)
    await loadQueue()
  } catch (err) {
    alert(err.response?.data?.message || 'Falha ao confirmar entrega.')
  } finally {
    actionLoading.value = null
  }
}

const formatMoney = (cents) => {
  return ((cents || 0) / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}

onMounted(() => {
  loadQueue()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Barra Superior da Esteira -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="p-3 bg-amber-500/10 text-amber-400 border border-amber-500/20 rounded-xl">
          <Boxes class="w-6 h-6" />
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-bold text-white tracking-tight">Fulfillment & Expedição (WMS)</h2>
            <span 
              class="text-[10px] font-extrabold px-2 py-0.5 rounded uppercase tracking-wider flex items-center gap-1"
              :class="mode === 'MANAGEMENT_VIEW' ? 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30' : 'bg-neutral-800 text-neutral-300 border border-neutral-700'"
            >
              <Eye v-if="mode === 'MANAGEMENT_VIEW'" class="w-3 h-3" />
              <EyeOff v-else class="w-3 h-3 text-amber-400" />
              <span>{{ mode === 'MANAGEMENT_VIEW' ? 'Modo Gerência (Preços Visíveis)' : 'Chão de Fábrica (Valores Ocultos)' }}</span>
            </span>
          </div>
          <p class="text-xs text-neutral-400 mt-0.5">Operação orientada a ID de pacote, conferência de cubagem e SKU.</p>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <select 
          v-model="filterStatus"
          @change="loadQueue"
          class="bg-neutral-800 border border-neutral-700 text-neutral-200 text-xs rounded-xl px-3 py-2 focus:outline-hidden"
        >
          <option value="ALL">Todas as Etapas</option>
          <option value="PAID">Aguardando Separação (PAID)</option>
          <option value="IN_PREPARATION">Em Picking / Bancada</option>
          <option value="SHIPPED">Despachado / Em Rota</option>
          <option value="DELIVERED">Entregue</option>
        </select>
        <button 
          @click="loadQueue" 
          class="p-2 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 rounded-xl transition"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
        </button>
      </div>
    </div>

    <!-- Lista da Fila -->
    <div v-if="isLoading" class="space-y-4 animate-pulse">
      <div v-for="n in 3" :key="n" class="bg-neutral-900 border border-neutral-800 rounded-2xl h-32" />
    </div>

    <div v-else-if="orders.length === 0" class="p-16 text-center bg-neutral-900 border border-neutral-800 rounded-2xl text-neutral-500 text-xs">
      Nenhum pacote pendente nesta etapa da esteira.
    </div>

    <div v-else class="space-y-4">
      <div 
        v-for="order in orders" 
        :key="order.order_id"
        class="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 hover:border-neutral-700 transition space-y-4"
      >
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-neutral-800/80 pb-3">
          <div class="flex items-center gap-3">
            <Barcode class="w-5 h-5 text-indigo-400" />
            <div>
              <div class="flex items-center gap-2">
                <span class="text-xs font-mono font-bold text-white tracking-wider">
                  PACOTE #{{ order.order_id }}
                </span>
                <span 
                  class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase"
                  :class="{
                    'bg-amber-500/20 text-amber-300 border border-amber-500/30': order.status === 'PAID',
                    'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30': order.status === 'IN_PREPARATION',
                    'bg-blue-500/20 text-blue-300 border border-blue-500/30': order.status === 'SHIPPED',
                    'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': order.status === 'DELIVERED',
                  }"
                >
                  {{ order.status }}
                </span>
              </div>
              <p class="text-[11px] text-neutral-400 mt-0.5">
                Modalidade: <strong class="text-neutral-200">{{ order.shipping_service || 'STANDARD' }}</strong>
                &bull; CEP Destino: <strong class="text-neutral-200">{{ order.shipping_zip_code || 'Não informado' }}</strong>
              </p>
            </div>
          </div>

          <!-- Métricas de Balança -->
          <div class="flex items-center gap-4 text-xs text-neutral-400 bg-neutral-950/60 border border-neutral-800 px-3 py-1.5 rounded-xl">
            <div class="flex items-center gap-1.5">
              <Scale class="w-3.5 h-3.5 text-amber-400" />
              <span>Peso Taxável: <strong class="text-white">{{ order.chargeable_weight_kg }} kg</strong></span>
            </div>
            <div class="border-l border-neutral-800 pl-3">
              <span>Físico: <strong class="text-neutral-300">{{ order.physical_weight_kg }} kg</strong></span>
            </div>
          </div>
        </div>

        <!-- Grade de Produtos no Pacote (Apenas SKUs e Quantidades) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
          <div 
            v-for="item in order.items" 
            :key="item.sku_id"
            class="bg-neutral-950/40 border border-neutral-800/80 rounded-xl p-3 text-xs flex items-center justify-between"
          >
            <div>
              <p class="font-medium text-neutral-200 truncate max-w-[200px]">{{ item.name }}</p>
              <p class="text-[10px] text-neutral-500 mt-0.5">
                SKU: {{ item.sku_id.slice(0, 8) }} &bull; Dim: {{ item.dimensions }}
              </p>
            </div>
            <span class="bg-neutral-800 text-white font-mono font-bold px-2 py-1 rounded-lg text-xs">
              x{{ item.quantity }}
            </span>
          </div>
        </div>

        <!-- Rodapé Operacional -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2 border-t border-neutral-800/50">
          <div class="flex items-center gap-2 text-xs">
            <span v-if="order.tracking_code" class="font-mono bg-indigo-950/40 border border-indigo-800/50 text-indigo-300 px-2.5 py-1 rounded-lg">
              Rastreio: {{ order.tracking_code }}
            </span>
            <span v-else class="text-neutral-500 text-[11px]">
              Aguardando pesagem/etiquetagem
            </span>

            <!-- Visível apenas para ADMIN -->
            <span v-if="order.total_order_cents" class="ml-2 text-neutral-400 border-l border-neutral-800 pl-3 text-[11px]">
              Valor NF: <strong class="text-emerald-400">{{ formatMoney(order.total_order_cents) }}</strong> (Frete: {{ formatMoney(order.shipping_cost_cents) }})
            </span>
          </div>

          <!-- Gatilhos Operacionais da Esteira -->
          <div class="flex items-center gap-2">
            <button 
              v-if="order.status === 'PAID'"
              @click="markInPreparation(order.order_id)"
              :disabled="actionLoading === order.order_id"
              class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold px-3 py-1.5 rounded-xl transition flex items-center gap-1.5"
            >
              <Package class="w-3.5 h-3.5" />
              <span>Bipar / Iniciar Picking</span>
            </button>

            <button 
              v-if="order.status === 'IN_PREPARATION' || order.status === 'PAID'"
              @click="shipOrder(order.order_id)"
              :disabled="actionLoading === order.order_id"
              class="bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold px-3 py-1.5 rounded-xl transition flex items-center gap-1.5"
            >
              <Truck class="w-3.5 h-3.5" />
              <span>Emitir Etiqueta & Despachar</span>
            </button>

            <button 
              v-if="order.status === 'SHIPPED'"
              @click="deliverOrder(order.order_id)"
              :disabled="actionLoading === order.order_id"
              class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-3 py-1.5 rounded-xl transition flex items-center gap-1.5"
            >
              <CheckCircle2 class="w-3.5 h-3.5" />
              <span>Confirmar Entrega</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
