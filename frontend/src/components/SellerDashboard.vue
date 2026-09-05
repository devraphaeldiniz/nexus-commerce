<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { 
  Building2, 
  ShieldCheck, 
  AlertTriangle, 
  Clock, 
  ArrowUpRight, 
  ArrowDownLeft, 
  Wallet, 
  Lock, 
  CheckCircle,
  XCircle,
  RefreshCw,
  Award,
  SendHorizontal,
  X,
  CheckCircle2
} from 'lucide-vue-next'

const sellers = ref([])
const selectedSellerId = ref(null)
const dashboardData = ref(null)
const isLoading = ref(true)
const isUpdatingStatus = ref(false)

// Estado do Modal de Saque Pix
const isPayoutModalOpen = ref(false)
const pixKeyType = ref('CNPJ')
const pixKey = ref('')
const payoutAmount = ref('')
const isProcessingPayout = ref(false)
const payoutSuccess = ref(null)
const payoutError = ref(null)

const loadSellers = async () => {
  try {
    const { data } = await axios.get('http://localhost:8000/api/sellers')
    sellers.value = data
    if (data.length > 0 && !selectedSellerId.value) {
      selectedSellerId.value = data[0].id
      await loadDashboard(data[0].id)
    }
  } catch (err) {
    console.error('Erro ao carregar lista de sellers:', err)
  }
}

const loadDashboard = async (sellerId) => {
  isLoading.value = true
  try {
    const { data } = await axios.get(`http://localhost:8000/api/sellers/${sellerId}/dashboard`)
    dashboardData.value = data
  } catch (err) {
    console.error('Erro ao carregar dashboard:', err)
  } finally {
    isLoading.value = false
  }
}

const handleSellerChange = (event) => {
  const newId = event.target.value
  selectedSellerId.value = newId
  loadDashboard(newId)
}

const transitionKyc = async (action) => {
  if (!selectedSellerId.value) return
  isUpdatingStatus.value = true
  try {
    await axios.post(`http://localhost:8000/api/sellers/${selectedSellerId.value}/kyc-transition`, {
      action,
      reason: `Ação manual executada via painel operacional (${action}).`,
    })
    await loadDashboard(selectedSellerId.value)
    await loadSellers()
  } catch (err) {
    alert(err.response?.data?.message || 'Falha ao atualizar status KYC.')
  } finally {
    isUpdatingStatus.value = false
  }
}

const handleRequestPayout = async () => {
  if (!payoutAmount.value || !pixKey.value) return

  const amountCents = Math.round(parseFloat(payoutAmount.value) * 100)
  if (isNaN(amountCents) || amountCents <= 0) {
    payoutError.value = 'Valor de saque inválido.'
    return
  }

  isProcessingPayout.value = true
  payoutError.value = null
  payoutSuccess.value = null

  try {
    const { data } = await axios.post(`http://localhost:8000/api/sellers/${selectedSellerId.value}/payout`, {
      pix_key_type: pixKeyType.value,
      pix_key: pixKey.value,
      amount_cents: amountCents,
    }, {
      headers: {
        'X-Idempotency-Key': 'payout-' + crypto.randomUUID(),
      }
    })

    payoutSuccess.value = data.payout
    await loadDashboard(selectedSellerId.value)
  } catch (err) {
    payoutError.value = err.response?.data?.error || err.response?.data?.message || 'Erro ao processar saque.'
  } finally {
    isProcessingPayout.value = false
  }
}

const formatMoney = (cents) => {
  return ((cents || 0) / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}

const formatDate = (dateString) => {
  if (!dateString) return '-'
  return new Date(dateString).toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

onMounted(() => {
  loadSellers()
})
</script>

<template>
  <div class="space-y-8">
    <!-- Topbar do Seller -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="p-3 bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 rounded-xl">
          <Building2 class="w-6 h-6" />
        </div>
        <div>
          <h2 class="text-lg font-bold text-white tracking-tight">Portal do Vendedor</h2>
          <p class="text-xs text-neutral-400">Auditoria financeira, split de pagamentos e liquidação Pix.</p>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <label class="text-xs text-neutral-400 font-medium">Lojista:</label>
        <select 
          :value="selectedSellerId" 
          @change="handleSellerChange"
          class="bg-neutral-800 border border-neutral-700 text-neutral-100 text-xs rounded-xl px-3 py-2 font-medium focus:outline-hidden focus:border-indigo-500"
        >
          <option v-for="s in sellers" :key="s.id" :value="s.id">
            {{ s.store_name }} ({{ s.kyc_status }})
          </option>
        </select>
        <button 
          @click="loadDashboard(selectedSellerId)" 
          class="p-2 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 rounded-xl transition"
        >
          <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isLoading }" />
        </button>
      </div>
    </div>

    <div v-if="isLoading" class="grid grid-cols-1 md:grid-cols-3 gap-6 animate-pulse">
      <div v-for="n in 3" :key="n" class="bg-neutral-900 border border-neutral-800 rounded-2xl h-36" />
    </div>

    <div v-else-if="dashboardData" class="space-y-8">
      <!-- Banner KYC -->
      <div 
        class="border rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4"
        :class="{
          'bg-emerald-950/20 border-emerald-800/50': dashboardData.seller.kyc_status === 'APPROVED',
          'bg-amber-950/20 border-amber-800/50': dashboardData.seller.kyc_status === 'PENDING',
          'bg-rose-950/20 border-rose-800/50': ['REJECTED', 'SUSPENDED'].includes(dashboardData.seller.kyc_status)
        }"
      >
        <div class="flex items-center gap-3">
          <ShieldCheck v-if="dashboardData.seller.kyc_status === 'APPROVED'" class="w-7 h-7 text-emerald-400" />
          <Clock v-else-if="dashboardData.seller.kyc_status === 'PENDING'" class="w-7 h-7 text-amber-400" />
          <AlertTriangle v-else class="w-7 h-7 text-rose-400" />
          
          <div>
            <div class="flex items-center gap-2">
              <span class="text-sm font-semibold text-white">Status de Governança KYC:</span>
              <span 
                class="text-xs font-bold px-2.5 py-0.5 rounded-full uppercase tracking-wider"
                :class="{
                  'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': dashboardData.seller.kyc_status === 'APPROVED',
                  'bg-amber-500/20 text-amber-300 border border-amber-500/30': dashboardData.seller.kyc_status === 'PENDING',
                  'bg-rose-500/20 text-rose-300 border border-rose-500/30': ['REJECTED', 'SUSPENDED'].includes(dashboardData.seller.kyc_status)
                }"
              >
                {{ dashboardData.seller.kyc_status }}
              </span>
            </div>
            <p class="text-xs text-neutral-400 mt-0.5">
              Documento: {{ dashboardData.seller.document_type }} {{ dashboardData.seller.document_number }} &bull; Razão Social: {{ dashboardData.seller.legal_name }}
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <button 
            v-if="dashboardData.seller.kyc_status !== 'APPROVED'"
            @click="transitionKyc('APPROVE')"
            :disabled="isUpdatingStatus"
            class="inline-flex items-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium px-3.5 py-2 rounded-xl transition shadow-md shadow-emerald-600/20"
          >
            <CheckCircle class="w-3.5 h-3.5" />
            <span>Aprovar Seller</span>
          </button>
          <button 
            v-if="dashboardData.seller.kyc_status === 'APPROVED'"
            @click="transitionKyc('SUSPEND')"
            :disabled="isUpdatingStatus"
            class="inline-flex items-center gap-1.5 bg-rose-600/20 hover:bg-rose-600 text-rose-300 hover:text-white border border-rose-500/30 text-xs font-medium px-3.5 py-2 rounded-xl transition"
          >
            <XCircle class="w-3.5 h-3.5" />
            <span>Suspender Operação</span>
          </button>
        </div>
      </div>

      <!-- Métricas Financeiras e Botão de Saque -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Saldo Disponível com Saque Pix -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between">
              <span class="text-xs font-medium text-neutral-400 uppercase tracking-wider">Saldo Sacável (Disponível)</span>
              <div class="p-2 bg-emerald-500/10 text-emerald-400 rounded-xl">
                <Wallet class="w-5 h-5" />
              </div>
            </div>
            <p class="text-2xl font-extrabold text-white mt-4">
              {{ formatMoney(dashboardData.wallet?.balance_available_cents) }}
            </p>
            <p class="text-[11px] text-neutral-500 mt-1">Líquido de comissão e liberado após entrega.</p>
          </div>

          <button 
            @click="isPayoutModalOpen = true; payoutSuccess = null; payoutError = null"
            :disabled="dashboardData.seller.kyc_status !== 'APPROVED' || (dashboardData.wallet?.balance_available_cents || 0) < 1000"
            class="mt-4 w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 disabled:hover:bg-emerald-600 text-white text-xs font-semibold py-2.5 rounded-xl transition flex items-center justify-center gap-1.5 shadow-md shadow-emerald-600/20"
          >
            <SendHorizontal class="w-4 h-4" />
            <span>Transferir via Pix</span>
          </button>
        </div>

        <!-- Saldo em Custódia (Escrow) -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 relative overflow-hidden">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-neutral-400 uppercase tracking-wider">Retido em Custódia (Escrow)</span>
            <div class="p-2 bg-amber-500/10 text-amber-400 rounded-xl">
              <Lock class="w-5 h-5" />
            </div>
          </div>
          <p class="text-2xl font-extrabold text-white mt-4">
            {{ formatMoney(dashboardData.wallet?.balance_escrow_cents) }}
          </p>
          <p class="text-[11px] text-neutral-500 mt-1">Garantia operacional protegida até a entrega.</p>
        </div>

        <!-- Reputação -->
        <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 relative overflow-hidden">
          <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-neutral-400 uppercase tracking-wider">Índice de Reputação</span>
            <div class="p-2 bg-indigo-500/10 text-indigo-400 rounded-xl">
              <Award class="w-5 h-5" />
            </div>
          </div>
          <div class="flex items-baseline gap-2 mt-4">
            <span class="text-2xl font-extrabold text-white">{{ dashboardData.seller.reputation_score }}</span>
            <span class="text-xs text-neutral-400">/ 5.00</span>
          </div>
          <p class="text-[11px] text-neutral-500 mt-1">
            {{ dashboardData.seller.total_sales_count }} vendas concluídas &bull; {{ dashboardData.seller.cancellation_rate }}% cancelamentos
          </p>
        </div>
      </div>

      <!-- Livro-Razão Contábil (Ledger) -->
      <div class="bg-neutral-900 border border-neutral-800 rounded-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-800 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-white tracking-tight">Livro-Razão Contábil (Ledger)</h3>
          <span class="text-xs text-neutral-500">Extrato em tempo real</span>
        </div>

        <div v-if="dashboardData.transactions.length === 0" class="p-12 text-center text-neutral-500 text-xs">
          Nenhuma movimentação financeira registrada.
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-neutral-950/40 text-neutral-400 uppercase font-medium tracking-wider border-b border-neutral-800/80">
              <tr>
                <th class="px-6 py-3">Tipo</th>
                <th class="px-6 py-3">Descrição</th>
                <th class="px-6 py-3">Valor</th>
                <th class="px-6 py-3">Data / Hora</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-800/60 text-neutral-300">
              <tr v-for="tx in dashboardData.transactions" :key="tx.id" class="hover:bg-neutral-800/20 transition">
                <td class="px-6 py-3.5">
                  <span 
                    class="inline-flex items-center gap-1 font-semibold px-2 py-0.5 rounded text-[10px]"
                    :class="{
                      'bg-amber-500/10 text-amber-400 border border-amber-500/20': tx.type === 'ESCROW_HOLD',
                      'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20': tx.type === 'ESCROW_RELEASE',
                      'bg-rose-500/10 text-rose-400 border border-rose-500/20': tx.type === 'REFUND_DEBIT',
                      'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20': tx.type === 'PAYOUT'
                    }"
                  >
                    <ArrowDownLeft v-if="tx.type === 'ESCROW_HOLD'" class="w-3 h-3" />
                    <ArrowUpRight v-else class="w-3 h-3" />
                    {{ tx.type }}
                  </span>
                </td>
                <td class="px-6 py-3.5 font-medium text-neutral-200">
                  {{ tx.description }}
                </td>
                <td class="px-6 py-3.5 font-bold" :class="tx.type === 'PAYOUT' ? 'text-rose-400' : tx.type === 'ESCROW_RELEASE' ? 'text-emerald-400' : 'text-neutral-200'">
                  {{ tx.type === 'PAYOUT' ? '-' : '' }}{{ formatMoney(tx.amount_cents) }}
                </td>
                <td class="px-6 py-3.5 text-neutral-500">
                  {{ formatDate(tx.created_at) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal de Solicitação de Saque Pix -->
    <div v-if="isPayoutModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-xs">
      <div class="w-full max-w-md bg-neutral-900 border border-neutral-800 rounded-2xl p-6 space-y-5 shadow-2xl">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
          <div class="flex items-center gap-2">
            <SendHorizontal class="w-5 h-5 text-emerald-400" />
            <h3 class="font-bold text-white text-sm">Transferência Pix (Payout)</h3>
          </div>
          <button @click="isPayoutModalOpen = false" class="text-neutral-400 hover:text-white">
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Feedback de Sucesso -->
        <div v-if="payoutSuccess" class="p-4 bg-emerald-950/40 border border-emerald-800/60 rounded-xl space-y-2 text-xs">
          <div class="flex items-center gap-2 text-emerald-300 font-semibold">
            <CheckCircle2 class="w-4 h-4" />
            <span>Saque Liquidado pelo Banco Central</span>
          </div>
          <p class="text-neutral-300">Valor debitado: <strong>{{ formatMoney(payoutSuccess.amount_cents) }}</strong></p>
          <p class="text-neutral-400 break-all">ID E2E: {{ payoutSuccess.bank_end_to_end_id }}</p>
          <button 
            @click="isPayoutModalOpen = false"
            class="w-full mt-2 bg-neutral-800 hover:bg-neutral-700 text-white font-medium py-1.5 rounded-lg transition"
          >
            Fechar
          </button>
        </div>

        <!-- Formulário de Saque -->
        <div v-else class="space-y-4 text-xs">
          <div v-if="payoutError" class="p-3 bg-rose-950/40 border border-rose-800/60 rounded-xl text-rose-300">
            {{ payoutError }}
          </div>

          <div>
            <label class="block text-neutral-300 font-medium mb-1">Tipo de Chave Pix</label>
            <select 
              v-model="pixKeyType"
              class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white focus:outline-hidden"
            >
              <option value="CNPJ">CNPJ</option>
              <option value="CPF">CPF</option>
              <option value="EMAIL">E-mail</option>
              <option value="PHONE">Telefone</option>
              <option value="RANDOM">Chave Aleatória (EVP)</option>
            </select>
          </div>

          <div>
            <label class="block text-neutral-300 font-medium mb-1">Chave Pix</label>
            <input 
              v-model="pixKey"
              type="text" 
              placeholder="Digite a chave Pix de destino"
              class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white focus:outline-hidden"
            />
          </div>

          <div>
            <div class="flex justify-between items-center mb-1">
              <label class="text-neutral-300 font-medium">Valor a Sacar (R$)</label>
              <span class="text-[11px] text-neutral-400">
                Disponível: {{ formatMoney(dashboardData.wallet?.balance_available_cents) }}
              </span>
            </div>
            <input 
              v-model="payoutAmount"
              type="number" 
              step="0.01"
              placeholder="0,00"
              class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-hidden"
            />
          </div>

          <button 
            @click="handleRequestPayout"
            :disabled="isProcessingPayout || !pixKey || !payoutAmount"
            class="w-full bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white font-medium py-2.5 rounded-xl transition flex items-center justify-center gap-2"
          >
            <span v-if="isProcessingPayout">Processando Transferência...</span>
            <span v-else>Confirmar Saque Imediato</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
