<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { useCartStore } from '../stores/cart'
import { useAuthStore } from '../stores/auth'
import { 
  X, 
  Trash2, 
  Plus, 
  Minus, 
  ShoppingBag, 
  ArrowRight, 
  Truck, 
  CreditCard,
  QrCode,
  ShieldCheck,
  Loader2,
  MapPin,
  Home
} from 'lucide-vue-next'

const router = useRouter()
const cart = useCartStore()
const auth = useAuthStore()

const cepInput = ref('')
const isLookingUpCep = ref(false)

// Campos de endereço
const streetInput = ref('')
const numberInput = ref('')
const complementInput = ref('')
const neighborhoodInput = ref('')
const cityInput = ref('')
const stateInput = ref('')

const numberInputRef = ref(null)

const shippingQuotes = ref([])
const selectedShipping = ref(null)
const isCheckingOut = ref(false)
const checkoutError = ref('')
const paymentMethod = ref('PIX')

const formatMoney = (cents) => {
  return ((cents || 0) / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}

const subtotalCents = computed(() => {
  return cart.items.reduce((acc, item) => acc + (item.price_cents * item.quantity), 0)
})

const shippingCents = computed(() => {
  return selectedShipping.value ? selectedShipping.value.price_cents : 0
})

const totalCents = computed(() => {
  return subtotalCents.value + shippingCents.value
})

const calculateShipping = async (cleanCep) => {
  try {
    const payload = {
      zip_code: cleanCep,
      items: cart.items.map(item => ({
        product_id: item.id,
        quantity: item.quantity,
        weight_grams: item.weight_grams || 350,
      }))
    }

    const { data } = await axios.post('/shipping/quote', payload)
    
    if (data && Array.isArray(data.quotes)) {
      shippingQuotes.value = data.quotes
    } else if (Array.isArray(data)) {
      shippingQuotes.value = data
    } else {
      shippingQuotes.value = []
    }

    if (shippingQuotes.value.length > 0) {
      selectedShipping.value = shippingQuotes.value[0]
    }
  } catch (err) {
    shippingQuotes.value = []
    selectedShipping.value = null
  }
}

const lookupViaCep = async () => {
  const cleanCep = cepInput.value.replace(/\D/g, '')
  if (cleanCep.length !== 8) {
    streetInput.value = ''
    neighborhoodInput.value = ''
    cityInput.value = ''
    stateInput.value = ''
    shippingQuotes.value = []
    selectedShipping.value = null
    return
  }

  isLookingUpCep.value = true
  try {
    const { data } = await axios.get(`https://viacep.com.br/ws/${cleanCep}/json/`)
    if (!data.erro) {
      streetInput.value = data.logradouro || ''
      neighborhoodInput.value = data.bairro || ''
      cityInput.value = data.localidade || ''
      stateInput.value = data.uf || ''

      // Foca automaticamente no campo Número
      nextTick(() => {
        if (numberInputRef.value) {
          numberInputRef.value.focus()
        }
      })
    }
    await calculateShipping(cleanCep)
  } catch (e) {
    await calculateShipping(cleanCep)
  } finally {
    isLookingUpCep.value = false
  }
}

// Busca automática assim que digitar os 8 dígitos
watch(cepInput, (newVal) => {
  const raw = (newVal || '').replace(/\D/g, '')
  if (raw.length === 8) {
    lookupViaCep()
  }
})

const handleCheckout = async () => {
  if (cart.items.length === 0) return

  if (!selectedShipping.value) {
    checkoutError.value = 'Informe seu CEP e selecione uma opção de frete.'
    return
  }

  if (!numberInput.value || numberInput.value.trim() === '') {
    checkoutError.value = 'Por favor, informe o número da sua residência.'
    if (numberInputRef.value) numberInputRef.value.focus()
    return
  }

  isCheckingOut.value = true
  checkoutError.value = ''

  try {
    
    const payload = {
      customer_name: auth.user?.name || 'Cliente Nexus',
      customer_email: auth.user?.email || 'cliente@nexus.com',
      shipping_zip_code: cepInput.value.replace(/\D/g, ''),
      shipping_street: streetInput.value,
      shipping_number: numberInput.value.trim(),
      shipping_complement: complementInput.value.trim() || null,
      shipping_neighborhood: neighborhoodInput.value,
      shipping_city: cityInput.value,
      shipping_state: stateInput.value,
      shipping_service: selectedShipping.value.service,
      shipping_cents: selectedShipping.value.price_cents,
      payment_method: paymentMethod.value,
      items: cart.items.map(i => ({
        product_id: i.id,
        quantity: i.quantity,
      }))
    }

    await axios.post('/checkout', payload, { withCredentials: true })
    
    cart.clearCart()
    cart.isOpen = false
    router.push('/orders')
  } catch (err) {
    if (err.response?.data?.errors) {
      const firstError = Object.values(err.response.data.errors)[0]
      checkoutError.value = Array.isArray(firstError) ? firstError[0] : firstError
    } else {
      checkoutError.value = err.response?.data?.message || 'Falha ao processar pedido.'
    }
  } finally {
    isCheckingOut.value = false
  }
}
</script>

<template>
  <div v-if="cart.isOpen" class="fixed inset-0 z-50 overflow-hidden">
    <div 
      class="absolute inset-0 bg-black/70 backdrop-blur-xs transition-opacity"
      @click="cart.isOpen = false"
    />

    <div class="fixed inset-y-0 right-0 max-w-full flex pl-10">
      <div class="w-screen max-w-md bg-neutral-900 border-l border-neutral-800 text-white flex flex-col shadow-2xl">
        
        <!-- Header -->
        <div class="p-5 border-b border-neutral-800 flex items-center justify-between">
          <div class="flex items-center gap-2.5">
            <ShoppingBag class="w-5 h-5 text-indigo-400" />
            <h2 class="text-sm font-black tracking-wide">Seu Carrinho</h2>
            <span class="bg-neutral-800 text-neutral-300 text-[10px] font-mono font-bold px-2 py-0.5 rounded-full">
              {{ cart.itemCount }}
            </span>
          </div>
          <button 
            @click="cart.isOpen = false"
            class="p-2 rounded-xl text-neutral-400 hover:text-white hover:bg-neutral-800 transition cursor-pointer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <!-- Lista de Itens -->
        <div class="flex-1 overflow-y-auto p-5 space-y-4">
          <div v-if="cart.items.length === 0" class="h-full flex flex-col items-center justify-center text-center space-y-3 py-12">
            <div class="w-14 h-14 rounded-2xl bg-neutral-800/80 border border-neutral-700/50 flex items-center justify-center text-neutral-500">
              <ShoppingBag class="w-6 h-6 stroke-1" />
            </div>
            <div>
              <p class="text-xs font-bold text-neutral-300">Seu carrinho está vazio</p>
              <p class="text-[11px] text-neutral-500">Adicione produtos para continuar.</p>
            </div>
          </div>

          <div 
            v-for="item in cart.items" 
            :key="item.id"
            class="bg-neutral-950 border border-neutral-800/80 rounded-2xl p-3 flex gap-3 items-center"
          >
            <img 
              :src="item.image_url" 
              :alt="item.name" 
              class="w-14 h-14 rounded-xl object-cover bg-neutral-900 border border-neutral-800 shrink-0" 
            />
            
            <div class="flex-1 min-w-0 space-y-1">
              <h4 class="text-xs font-bold text-neutral-200 truncate">{{ item.name }}</h4>
              <p class="text-xs font-black text-indigo-400 font-mono">{{ formatMoney(item.price_cents) }}</p>

              <div class="flex items-center gap-2 pt-1">
                <button 
                  @click="cart.decrementQuantity(item.id)"
                  class="w-6 h-6 rounded-lg bg-neutral-800 border border-neutral-700 flex items-center justify-center text-neutral-300 hover:text-white transition cursor-pointer"
                >
                  <Minus class="w-3 h-3" />
                </button>
                <span class="text-xs font-mono font-bold text-white px-1">{{ item.quantity }}</span>
                <button 
                  @click="cart.incrementQuantity(item.id)"
                  class="w-6 h-6 rounded-lg bg-neutral-800 border border-neutral-700 flex items-center justify-center text-neutral-300 hover:text-white transition cursor-pointer"
                >
                  <Plus class="w-3 h-3" />
                </button>
              </div>
            </div>

            <button 
              @click="cart.removeItem(item.id)"
              class="text-neutral-500 hover:text-rose-400 p-2 transition shrink-0 cursor-pointer"
            >
              <Trash2 class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- Entrega & Fechamento -->
        <div v-if="cart.items.length > 0" class="p-5 border-t border-neutral-800 bg-neutral-950 space-y-4 max-h-[50vh] overflow-y-auto">
          
          <!-- CEP e Busca Automática -->
          <div class="space-y-2">
            <label class="text-[11px] font-semibold text-neutral-400 flex items-center justify-between">
              <span class="flex items-center gap-1.5">
                <Truck class="w-3.5 h-3.5 text-indigo-400" />
                <span>CEP de Entrega (busca automática)</span>
              </span>
              <Loader2 v-if="isLookingUpCep" class="w-3.5 h-3.5 text-indigo-400 animate-spin" />
            </label>

            <input 
              v-model="cepInput"
              type="text" 
              maxlength="9"
              placeholder="Digite o CEP (ex: 33200-000)"
              class="w-full bg-neutral-900 border border-neutral-700 rounded-xl px-3 py-2 text-xs text-white font-mono placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden transition"
            />

            <!-- Dados do Endereço preenchidos automaticamente -->
            <div v-if="cityInput" class="space-y-2 pt-1">
              <div class="bg-neutral-900/80 border border-neutral-800 rounded-xl p-2.5 text-[11px] text-neutral-300 flex items-start gap-2">
                <MapPin class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5" />
                <div class="leading-relaxed">
                  <p class="font-bold text-white">{{ streetInput || 'Logradouro não informado' }}</p>
                  <p class="text-neutral-400">{{ neighborhoodInput }} • {{ cityInput }}/{{ stateInput }}</p>
                </div>
              </div>

              <!-- Número e Complemento -->
              <div class="grid grid-cols-2 gap-2">
                <div>
                  <label class="text-[10px] font-semibold text-neutral-400 block mb-1">
                    Número <span class="text-rose-400">*</span>
                  </label>
                  <input 
                    ref="numberInputRef"
                    v-model="numberInput"
                    type="text" 
                    placeholder="Ex: 120"
                    class="w-full bg-neutral-900 border border-neutral-700 focus:border-indigo-500 rounded-xl px-3 py-1.5 text-xs text-white font-mono focus:outline-hidden"
                  />
                </div>
                <div>
                  <label class="text-[10px] font-semibold text-neutral-400 block mb-1">
                    Complemento
                  </label>
                  <input 
                    v-model="complementInput"
                    type="text" 
                    placeholder="Apto, Bloco..."
                    class="w-full bg-neutral-900 border border-neutral-700 focus:border-indigo-500 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-hidden"
                  />
                </div>
              </div>
            </div>

            <!-- Opções de Frete -->
            <div v-if="shippingQuotes.length > 0" class="space-y-1.5 pt-2">
              <div 
                v-for="quote in shippingQuotes" 
                :key="quote.service"
                @click="selectedShipping = quote"
                class="p-2.5 rounded-xl border text-xs flex items-center justify-between cursor-pointer transition"
                :class="selectedShipping?.service === quote.service 
                  ? 'bg-indigo-600/10 border-indigo-500/60 text-white' 
                  : 'bg-neutral-900 border-neutral-800 text-neutral-400 hover:border-neutral-700'"
              >
                <div class="flex items-center gap-2">
                  <div 
                    class="w-3.5 h-3.5 rounded-full border flex items-center justify-center shrink-0"
                    :class="selectedShipping?.service === quote.service ? 'border-indigo-500 bg-indigo-500' : 'border-neutral-600'"
                  >
                    <div v-if="selectedShipping?.service === quote.service" class="w-1.5 h-1.5 bg-white rounded-full" />
                  </div>
                  <div>
                    <p class="font-bold text-white leading-none">{{ quote.name }}</p>
                    <span class="text-[10px] text-neutral-400">Entrega em até {{ quote.delivery_time_days }} dias</span>
                  </div>
                </div>
                <strong class="font-mono text-white">{{ formatMoney(quote.price_cents) }}</strong>
              </div>
            </div>
          </div>

          <!-- Meio de Pagamento -->
          <div class="space-y-1.5">
            <span class="text-[11px] font-semibold text-neutral-400 block">Forma de Pagamento</span>
            <div class="grid grid-cols-2 gap-2">
              <button 
                @click="paymentMethod = 'PIX'"
                type="button"
                class="p-2.5 rounded-xl border text-xs font-bold flex items-center justify-center gap-2 transition cursor-pointer"
                :class="paymentMethod === 'PIX' 
                  ? 'bg-emerald-600/10 border-emerald-500/60 text-emerald-400' 
                  : 'bg-neutral-900 border-neutral-800 text-neutral-400 hover:border-neutral-700'"
              >
                <QrCode class="w-3.5 h-3.5" />
                <span>PIX Instantâneo</span>
              </button>

              <button 
                @click="paymentMethod = 'CREDIT_CARD'"
                type="button"
                class="p-2.5 rounded-xl border text-xs font-bold flex items-center justify-center gap-2 transition cursor-pointer"
                :class="paymentMethod === 'CREDIT_CARD' 
                  ? 'bg-indigo-600/10 border-indigo-500/60 text-indigo-400' 
                  : 'bg-neutral-900 border-neutral-800 text-neutral-400 hover:border-neutral-700'"
              >
                <CreditCard class="w-3.5 h-3.5" />
                <span>Cartão</span>
              </button>
            </div>
          </div>

          <!-- Totalizadores -->
          <div class="space-y-1 pt-2 border-t border-neutral-800/80 text-xs">
            <div class="flex justify-between text-neutral-400">
              <span>Subtotal</span>
              <span class="font-mono text-white">{{ formatMoney(subtotalCents) }}</span>
            </div>
            <div class="flex justify-between text-neutral-400">
              <span>Frete</span>
              <span class="font-mono text-white">{{ selectedShipping ? formatMoney(shippingCents) : 'A calcular' }}</span>
            </div>
            <div class="flex justify-between text-sm font-bold text-white pt-2 border-t border-neutral-800">
              <span>Total Final</span>
              <span class="font-mono text-base font-black text-emerald-400">{{ formatMoney(totalCents) }}</span>
            </div>
          </div>

          <!-- Mensagem de Erro de Validação -->
          <p v-if="checkoutError" class="text-[11px] text-rose-400 bg-rose-950/40 p-2.5 rounded-xl border border-rose-900">
            {{ checkoutError }}
          </p>

          <!-- Botão de Finalizar -->
          <button 
            @click="handleCheckout"
            :disabled="isCheckingOut || !selectedShipping"
            class="w-full py-3 px-4 rounded-xl font-bold text-xs bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:cursor-not-allowed text-white transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20 cursor-pointer"
          >
            <Loader2 v-if="isCheckingOut" class="w-4 h-4 animate-spin" />
            <span v-else>Confirmar Pedido & Pagar</span>
            <ArrowRight v-if="!isCheckingOut" class="w-4 h-4" />
          </button>

          <p class="text-[10px] text-center text-neutral-500 flex items-center justify-center gap-1">
            <ShieldCheck class="w-3.5 h-3.5 text-emerald-400" />
            <span>Garantia Nexus: Pagamento retido até a entrega</span>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>
