<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { 
  ShieldAlert, 
  Send, 
  CheckCircle2, 
  MessageSquare, 
  AlertCircle,
  HelpCircle,
  Gavel
} from 'lucide-vue-next'

const orderId = ref('')
const customerEmail = ref('')
const reason = ref('PRODUCT_DEFECTIVE')
const description = ref('')
const isSubmitting = ref(false)
const claimError = ref(null)

// Visualizador de Disputa Ativa
const activeDispute = ref(null)
const newMessage = ref('')
const senderType = ref('CUSTOMER')
const resolutionNotes = ref('')
const isResolving = ref(false)

const openDispute = async () => {
  isSubmitting.value = true
  claimError.value = null
  try {
    const { data } = await axios.post('http://localhost:8000/api/disputes/open', {
      order_id: orderId.value,
      customer_email: customerEmail.value,
      reason: reason.value,
      description: description.value,
    })
    activeDispute.value = data.dispute
  } catch (err) {
    claimError.value = err.response?.data?.message || 'Erro ao abrir mediação.'
  } finally {
    isSubmitting.value = false
  }
}

const sendMessage = async () => {
  if (!newMessage.value || !activeDispute.value) return
  try {
    const { data } = await axios.post(`http://localhost:8000/api/disputes/${activeDispute.value.id}/messages`, {
      sender_type: senderType.value,
      sender_name: senderType.value === 'CUSTOMER' ? customerEmail.value : 'Nexus Support',
      message: newMessage.value,
    })
    activeDispute.value.messages.push(data)
    newMessage.value = ''
  } catch (err) {
    alert('Erro ao enviar mensagem.')
  }
}

const resolveDispute = async (decision) => {
  if (!resolutionNotes.value || !activeDispute.value) return
  isResolving.value = true
  try {
    const { data } = await axios.post(`http://localhost:8000/api/disputes/${activeDispute.value.id}/resolve`, {
      resolution: decision,
      notes: resolutionNotes.value,
    })
    activeDispute.value = data.dispute
    resolutionNotes.value = ''
  } catch (err) {
    alert(err.response?.data?.message || 'Falha ao arbitrar disputa.')
  } finally {
    isResolving.value = false
  }
}

const formatMoney = (cents) => {
  return ((cents || 0) / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}
</script>

<template>
  <div class="space-y-8 max-w-5xl mx-auto">
    <!-- Cabeçalho -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <div class="p-3 bg-rose-500/10 text-rose-400 border border-rose-500/20 rounded-xl">
          <ShieldAlert class="w-6 h-6" />
        </div>
        <div>
          <h2 class="text-lg font-bold text-white tracking-tight">Centro de Mediação e Devoluções</h2>
          <p class="text-xs text-neutral-400">Proteção de ponta a ponta com intermediação financeira.</p>
        </div>
      </div>
      <span class="text-xs font-semibold px-3 py-1 bg-neutral-800 text-neutral-300 rounded-full border border-neutral-700">
        Garantia Nexus
      </span>
    </div>

    <!-- Formulário de Abertura (Caso não haja disputa ativa carregada) -->
    <div v-if="!activeDispute" class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 space-y-6">
      <h3 class="text-sm font-semibold text-white">Solicitar Intervenção em um Pedido</h3>

      <div v-if="claimError" class="p-4 bg-rose-950/40 border border-rose-800/60 rounded-xl flex items-center gap-3 text-rose-300 text-xs">
        <AlertCircle class="w-5 h-5 shrink-0 text-rose-400" />
        <span>{{ claimError }}</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-neutral-300 mb-1">ID do Pedido (UUID)</label>
          <input 
            v-model="orderId" 
            type="text" 
            placeholder="Ex: a2ac9b52-5295-4506-af70..."
            class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-hidden"
          />
        </div>
        <div>
          <label class="block text-xs font-medium text-neutral-300 mb-1">E-mail do Comprador</label>
          <input 
            v-model="customerEmail" 
            type="email" 
            placeholder="email@utilizado.na.compra.com"
            class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-hidden"
          />
        </div>
      </div>

      <div>
        <label class="block text-xs font-medium text-neutral-300 mb-1">Motivo da Reclamação</label>
        <select 
          v-model="reason"
          class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-hidden"
        >
          <option value="PRODUCT_DEFECTIVE">Produto com defeito ou avaria</option>
          <option value="PRODUCT_DIFFERENT">Recebi um produto diferente do anúncio</option>
          <option value="PRODUCT_NOT_RECEIVED">Atraso na entrega / Não recebi o pacote</option>
          <option value="REGRET_OF_PURCHASE">Arrependimento de compra (Direito de devolução CDC)</option>
        </select>
      </div>

      <div>
        <label class="block text-xs font-medium text-neutral-300 mb-1">Relato detalhado</label>
        <textarea 
          v-model="description"
          rows="3"
          placeholder="Explique detalhadamente o ocorrido para o lojista e a moderação..."
          class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-hidden"
        />
      </div>

      <button 
        @click="openDispute"
        :disabled="isSubmitting || !orderId || !customerEmail || !description"
        class="bg-rose-600 hover:bg-rose-500 disabled:opacity-50 text-white font-medium text-xs px-5 py-2.5 rounded-xl transition shadow-lg shadow-rose-600/20 flex items-center gap-2"
      >
        <ShieldAlert class="w-4 h-4" />
        <span>{{ isSubmitting ? 'Registrando Disputa...' : 'Abrir Reclamação Formal' }}</span>
      </button>
    </div>

    <!-- Sala de Mediação Ativa -->
    <div v-else class="space-y-6">
      <!-- Status Card -->
      <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h3 class="text-sm font-semibold text-white">Disputa #{{ activeDispute.id }}</h3>
            <span 
              class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase"
              :class="{
                'bg-amber-500/20 text-amber-300 border border-amber-500/30': activeDispute.status === 'OPEN',
                'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': activeDispute.status === 'RESOLVED_REFUNDED',
                'bg-blue-500/20 text-blue-300 border border-blue-500/30': activeDispute.status === 'RESOLVED_FAVOR_SELLER'
              }"
            >
              {{ activeDispute.status }}
            </span>
          </div>
          <p class="text-xs text-neutral-400 mt-1">
            Motivo: <strong class="text-white">{{ activeDispute.reason }}</strong> &bull; Valor contestado: <strong class="text-emerald-400">{{ formatMoney(activeDispute.disputed_amount_cents) }}</strong>
          </p>
        </div>

        <button 
          @click="activeDispute = null"
          class="text-xs text-neutral-400 hover:text-white underline self-start md:self-auto"
        >
          Consultar outro pedido
        </button>
      </div>

      <!-- Histórico de Mensagens / Chat de Mediação -->
      <div class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 space-y-4">
        <h4 class="text-xs font-semibold text-neutral-300 flex items-center gap-1.5">
          <MessageSquare class="w-3.5 h-3.5 text-indigo-400" />
          <span>Canal de Mediação com o Vendedor</span>
        </h4>

        <div class="space-y-3 max-h-96 overflow-y-auto pr-2">
          <div 
            v-for="msg in activeDispute.messages" 
            :key="msg.id"
            class="p-3.5 rounded-xl text-xs space-y-1"
            :class="{
              'bg-indigo-950/30 border border-indigo-800/40 ml-8': msg.sender_type === 'CUSTOMER',
              'bg-neutral-800/60 border border-neutral-700 mr-8': msg.sender_type === 'SELLER',
              'bg-emerald-950/30 border border-emerald-800/40 text-emerald-200': msg.sender_type === 'MEDIATOR'
            }"
          >
            <div class="flex items-center justify-between text-[11px] text-neutral-400 font-medium">
              <span>{{ msg.sender_name }} ({{ msg.sender_type }})</span>
              <span>{{ new Date(msg.created_at).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }}</span>
            </div>
            <p class="text-neutral-200">{{ msg.message }}</p>
          </div>
        </div>

        <!-- Enviar Nova Mensagem -->
        <div v-if="!activeDispute.resolved_at" class="flex gap-2 pt-2 border-t border-neutral-800">
          <select 
            v-model="senderType"
            class="bg-neutral-800 border border-neutral-700 rounded-xl px-2.5 py-1.5 text-xs text-white focus:outline-hidden"
          >
            <option value="CUSTOMER">Comprador</option>
            <option value="SELLER">Vendedor</option>
            <option value="MEDIATOR">Moderador Nexus</option>
          </select>
          <input 
            v-model="newMessage"
            type="text" 
            placeholder="Digite uma mensagem ou anexe evidências..."
            @keyup.enter="sendMessage"
            class="flex-1 bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-1.5 text-xs text-white focus:border-indigo-500 focus:outline-hidden"
          />
          <button 
            @click="sendMessage"
            class="bg-indigo-600 hover:bg-indigo-500 text-white p-2 rounded-xl transition"
          >
            <Send class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Arbitragem da Moderação / Resolução Financeira -->
      <div v-if="!activeDispute.resolved_at" class="bg-neutral-900 border border-neutral-800 rounded-2xl p-6 space-y-4">
        <h4 class="text-xs font-semibold text-white flex items-center gap-1.5">
          <Gavel class="w-4 h-4 text-amber-400" />
          <span>Arbitragem e Decisão Final (Tribunal Nexus)</span>
        </h4>

        <div>
          <label class="block text-xs font-medium text-neutral-400 mb-1">Parecer da Moderação</label>
          <input 
            v-model="resolutionNotes"
            type="text" 
            placeholder="Ex: Devolução confirmada pelo código de postagem reversa."
            class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-xs text-white focus:border-indigo-500 focus:outline-hidden"
          />
        </div>

        <div class="flex gap-3">
          <button 
            @click="resolveDispute('REFUND_BUYER')"
            :disabled="isResolving || !resolutionNotes"
            class="flex-1 bg-rose-600/20 hover:bg-rose-600 text-rose-300 hover:text-white border border-rose-500/30 text-xs font-medium py-2.5 rounded-xl transition disabled:opacity-50"
          >
            Estornar Comprador (Debitar Carteira do Seller)
          </button>
          <button 
            @click="resolveDispute('FAVOR_SELLER')"
            :disabled="isResolving || !resolutionNotes"
            class="flex-1 bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white border border-emerald-500/30 text-xs font-medium py-2.5 rounded-xl transition disabled:opacity-50"
          >
            Decisão Favorável ao Seller (Liberar Saldo Retido)
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
