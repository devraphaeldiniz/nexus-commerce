<script setup>
import { ref, computed, onMounted } from 'vue'
import { useCartStore } from '../stores/cart'
import { useAuthStore } from '../stores/auth'
import axios from 'axios'
import { 
  ShoppingBag, 
  X, 
  Plus, 
  Minus, 
  Trash2, 
  CheckCircle2, 
  AlertCircle, 
  Truck, 
  CreditCard, 
  QrCode, 
  ArrowRight, 
  ArrowLeft, 
  MapPin, 
  Check, 
  Copy,
  Wallet
} from 'lucide-vue-next'

const cart = useCartStore()
const auth = useAuthStore()

// Formulário de Endereço de Entrega
const address = ref({
  zip_code: '',
  street: '',
  number: '',
  complement: '',
  neighborhood: '',
  city: '',
  state: '',
})
const isFetchingViaCep = ref(false)
const viaCepError = ref('')

// Opções e Seleção de Frete
const shippingOptions = ref([])
const selectedShipping = ref(null)
const isCalculatingShipping = ref(false)

// Método de Pagamento: 'PIX' | 'CREDIT_CARD'
const paymentMethod = ref('PIX')
const selectedCardId = ref('NEW')
const savedCards = ref([])

const creditCard = ref({
  number: '',
  holder_name: '',
  expiry: '',
  cvv: '',
  installments: 1,
})

const isProcessingCheckout = ref(false)
const orderSuccess = ref(null)
const orderError = ref(null)
const copiedPix = ref(false)

// Desconto no Pix: 10%
const pixDiscountPercent = 10
const pixTotalCents = computed(() => {
  const shippingCents = selectedShipping.value?.cost_cents || 0
  const subtotalWithDiscount = cart.totalCents * (1 - pixDiscountPercent / 100)
  return Math.round(subtotalWithDiscount + shippingCents)
})

const finalGrandTotalCents = computed(() => {
  const shippingCents = selectedShipping.value?.cost_cents || 0
  return cart.totalCents + shippingCents
})

const currentPayableCents = computed(() => {
  return paymentMethod.value === 'PIX' ? pixTotalCents.value : finalGrandTotalCents.value
})

const installmentOptions = computed(() => {
  const total = finalGrandTotalCents.value
  const list = []
  for (let i = 1; i <= 12; i++) {
    const installmentValue = Math.round(total / i)
    list.push({
      count: i,
      cents: installmentValue,
      label: `${i}x de ${formatMoney(installmentValue)} sem juros`,
    })
  }
  return list
})

const loadSavedCards = async () => {
  if (!auth.isAuthenticated) return
  try {
    const { data } = await axios.get('http://localhost:8000/api/profile/cards')
    savedCards.value = data
    if (data.length > 0) {
      selectedCardId.value = data[0].id
    }
  } catch (err) {
    console.error('Erro ao carregar cartões:', err)
  }
}

// Busca de CEP Real
const lookupViaCep = async () => {
  const clean = address.value.zip_code.replace(/\D/g, '')
  if (clean.length !== 8) return

  isFetchingViaCep.value = true
  viaCepError.value = ''

  try {
    const { data } = await axios.get(`https://viacep.com.br/ws/${clean}/json/`)
    if (data.erro) {
      viaCepError.value = 'CEP não localizado na base dos Correios.'
      return
    }

    address.value.street = data.logradouro || ''
    address.value.neighborhood = data.bairro || ''
    address.value.city = data.localidade || ''
    address.value.state = data.uf || ''

    await calculateShipping(clean)
  } catch (err) {
    viaCepError.value = 'Erro ao consultar CEP.'
  } finally {
    isFetchingViaCep.value = false
  }
}

const calculateShipping = async (cleanZip) => {
  isCalculatingShipping.value = true
  try {
    const { data } = await axios.post('http://localhost:8000/api/shipping/quote', {
      zip_code: cleanZip,
      items: cart.items.map(i => ({ id: i.id, quantity: i.quantity })),
    })
    shippingOptions.value = data.options
    if (data.options.length > 0) {
      selectedShipping.value = data.options[0]
    }
  } catch (err) {
    console.error('Erro na cotação:', err)
  } finally {
    isCalculatingShipping.value = false
  }
}

const copyPixPayload = (text) => {
  navigator.clipboard.writeText(text)
  copiedPix.value = true
  setTimeout(() => copiedPix.value = false, 3000)
}

const handleProcessOrder = async () => {
  isProcessingCheckout.value = true
  orderError.value = null

  const idempotencyKey = 'order-' + crypto.randomUUID()

  try {
    const payload = {
      customer_email: auth.user?.email,
      shipping_zip_code: address.value.zip_code,
      shipping_service: selectedShipping.value?.service_code || 'STANDARD',
      shipping_cost_cents: selectedShipping.value?.cost_cents || 0,
      estimated_delivery_days: selectedShipping.value?.days || null,
      items: cart.items.map(i => ({ id: i.id, quantity: i.quantity })),
    }

    const { data } = await axios.post('http://localhost:8000/api/checkout', payload, {
      headers: {
        'Content-Type': 'application/json',
        'X-Idempotency-Key': idempotencyKey,
      },
    })

    const fakePixCopiaECola = `00020126580014br.gov.bcb.pix0136${data.id}520400005303986540${(currentPayableCents.value / 100).toFixed(2)}5802BR5914NEXUSCOMMERCE6009SAOPAULO62070503***6304`

    orderSuccess.value = {
      ...data,
      pix_copy_paste: fakePixCopiaECola,
      payment_type: paymentMethod.value,
      installments_chosen: creditCard.value.installments,
    }

    cart.clearCart()
  } catch (err) {
    orderError.value = err.response?.data?.error || err.response?.data?.message || 'Falha ao processar compra.'
  } finally {
    isProcessingCheckout.value = false
  }
}

const formatMoney = (cents) => {
  return ((cents || 0) / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}

onMounted(() => {
  loadSavedCards()
})
</script>

<template>
  <div v-if="cart.isCartOpen" class="fixed inset-0 z-50 overflow-hidden">
    <div 
      class="absolute inset-0 bg-neutral-950/70 backdrop-blur-xs transition-opacity" 
      @click="cart.toggleCart"
    />

    <div class="fixed inset-y-0 right-0 max-w-full flex pl-4 sm:pl-10">
      <div class="w-screen max-w-lg bg-neutral-900 border-l border-neutral-800 text-neutral-100 flex flex-col shadow-2xl">
        
        <!-- Header com Stepper -->
        <div class="p-5 border-b border-neutral-800/80">
          <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-2">
              <ShoppingBag class="w-5 h-5 text-indigo-400" />
              <h2 class="text-sm font-black text-white tracking-tight">Checkout Nexus</h2>
            </div>
            <button 
              @click="cart.toggleCart" 
              class="p-1.5 text-neutral-400 hover:text-white hover:bg-neutral-800 rounded-xl transition"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <div class="flex items-center justify-between text-xs px-2">
            <div class="flex items-center gap-1.5 font-bold" :class="cart.checkoutStep >= 1 ? 'text-indigo-400' : 'text-neutral-500'">
              <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]" :class="cart.checkoutStep >= 1 ? 'bg-indigo-600 text-white' : 'bg-neutral-800 text-neutral-400'">1</span>
              <span>Carrinho</span>
            </div>
            <div class="flex-1 h-0.5 mx-2" :class="cart.checkoutStep >= 2 ? 'bg-indigo-600' : 'bg-neutral-800'" />
            <div class="flex items-center gap-1.5 font-bold" :class="cart.checkoutStep >= 2 ? 'text-indigo-400' : 'text-neutral-500'">
              <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]" :class="cart.checkoutStep >= 2 ? 'bg-indigo-600 text-white' : 'bg-neutral-800 text-neutral-400'">2</span>
              <span>Entrega</span>
            </div>
            <div class="flex-1 h-0.5 mx-2" :class="cart.checkoutStep >= 3 ? 'bg-indigo-600' : 'bg-neutral-800'" />
            <div class="flex items-center gap-1.5 font-bold" :class="cart.checkoutStep >= 3 ? 'text-indigo-400' : 'text-neutral-500'">
              <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px]" :class="cart.checkoutStep >= 3 ? 'bg-indigo-600 text-white' : 'bg-neutral-800 text-neutral-400'">3</span>
              <span>Pagamento</span>
            </div>
          </div>
        </div>

        <!-- Sucesso do Pedido -->
        <div v-if="orderSuccess" class="flex-1 overflow-y-auto p-6 space-y-5 text-center">
          <div class="w-14 h-14 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center mx-auto">
            <CheckCircle2 class="w-7 h-7" />
          </div>

          <div>
            <h3 class="text-lg font-black text-white">Pedido Concluído com Sucesso!</h3>
            <p class="text-xs text-neutral-400 mt-0.5">Identificador: {{ orderSuccess.id }}</p>
          </div>

          <div v-if="orderSuccess.payment_type === 'PIX'" class="bg-neutral-950/80 border border-neutral-800 rounded-2xl p-5 space-y-4 text-left text-xs">
            <div class="flex items-center justify-between border-b border-neutral-800 pb-2.5">
              <span class="font-bold text-emerald-400 flex items-center gap-1.5">
                <QrCode class="w-4 h-4" />
                <span>Pague com Pix (-10% Aplicado)</span>
              </span>
              <strong class="text-sm font-black text-white">{{ formatMoney(currentPayableCents) }}</strong>
            </div>

            <div class="flex flex-col items-center justify-center p-3 bg-white rounded-xl w-44 mx-auto shadow-lg">
              <img 
                :src="`https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=${encodeURIComponent(orderSuccess.pix_copy_paste)}`" 
                alt="QR Code Pix"
                class="w-36 h-36"
              />
            </div>

            <div class="space-y-1.5">
              <label class="text-[11px] font-semibold text-neutral-400">Código Pix Copia e Cola:</label>
              <div class="flex gap-2">
                <input 
                  type="text" 
                  readonly 
                  :value="orderSuccess.pix_copy_paste"
                  class="w-full bg-neutral-900 border border-neutral-800 rounded-lg px-2.5 py-2 text-[10px] font-mono text-neutral-300 select-all"
                />
                <button 
                  @click="copyPixPayload(orderSuccess.pix_copy_paste)"
                  class="bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1 shrink-0"
                >
                  <Check v-if="copiedPix" class="w-3.5 h-3.5" />
                  <Copy v-else class="w-3.5 h-3.5" />
                  <span>{{ copiedPix ? 'Copiado!' : 'Copiar' }}</span>
                </button>
              </div>
            </div>
          </div>

          <div v-else class="bg-neutral-950/80 border border-neutral-800 rounded-2xl p-4 text-left text-xs space-y-2">
            <div class="flex justify-between">
              <span class="text-neutral-400">Modalidade:</span>
              <strong class="text-white">Cartão de Crédito</strong>
            </div>
            <div class="flex justify-between">
              <span class="text-neutral-400">Plano Escolhido:</span>
              <strong class="text-white">{{ orderSuccess.installments_chosen }}x de {{ formatMoney(finalGrandTotalCents / orderSuccess.installments_chosen) }} (sem juros)</strong>
            </div>
            <div class="flex justify-between">
              <span class="text-neutral-400">Status:</span>
              <span class="text-emerald-400 font-bold">PAGAMENTO APROVADO</span>
            </div>
          </div>

          <button 
            @click="orderSuccess = null; cart.toggleCart()" 
            class="w-full bg-neutral-800 hover:bg-neutral-700 text-white font-bold py-3 rounded-xl transition text-xs"
          >
            Voltar para a Loja
          </button>
        </div>

        <!-- STEP 1: ITENS -->
        <div v-else-if="cart.checkoutStep === 1" class="flex-1 flex flex-col justify-between overflow-hidden">
          <div class="flex-1 overflow-y-auto px-5 py-4 divide-y divide-neutral-800/80">
            <div v-if="cart.items.length === 0" class="h-full flex flex-col items-center justify-center text-neutral-500 space-y-2">
              <ShoppingBag class="w-10 h-10 stroke-1 text-neutral-600" />
              <p class="text-xs">Seu carrinho está vazio.</p>
            </div>

            <div 
              v-for="item in cart.items" 
              :key="item.id"
              class="py-3.5 flex gap-3.5 items-center first:pt-0 last:pb-0"
            >
              <img :src="item.image_url" :alt="item.name" class="w-14 h-14 object-cover rounded-xl bg-neutral-800 shrink-0" />
              <div class="flex-1 min-w-0">
                <div class="flex items-start justify-between gap-1">
                  <h4 class="text-xs font-semibold text-neutral-200 truncate">{{ item.name }}</h4>
                  <button @click="cart.removeItem(item.id)" class="text-neutral-500 hover:text-rose-400 p-1">
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </div>
                <p class="text-xs font-bold text-white mt-0.5">{{ formatMoney(item.price_cents) }}</p>

                <div class="mt-2 flex items-center justify-between">
                  <div class="flex items-center bg-neutral-950 border border-neutral-800 rounded-lg">
                    <button @click="cart.updateQuantity(item.id, item.quantity - 1)" class="p-1 text-neutral-400 hover:text-white">
                      <Minus class="w-3 h-3" />
                    </button>
                    <span class="text-xs font-bold px-2 font-mono text-white">{{ item.quantity }}</span>
                    <button @click="cart.updateQuantity(item.id, item.quantity + 1)" class="p-1 text-neutral-400 hover:text-white" :disabled="item.quantity >= item.stock_quantity">
                      <Plus class="w-3 h-3" />
                    </button>
                  </div>
                  <span class="text-xs font-bold text-indigo-400 font-mono">{{ formatMoney(item.price_cents * item.quantity) }}</span>
                </div>
              </div>
            </div>
          </div>

          <div v-if="cart.items.length > 0" class="p-5 bg-neutral-950/80 border-t border-neutral-800 space-y-3">
            <div class="flex justify-between items-center text-xs">
              <span class="text-neutral-400">Subtotal dos produtos:</span>
              <span class="text-sm font-bold text-white">{{ cart.formattedTotal }}</span>
            </div>

            <button 
              @click="cart.setStep(2)"
              class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3.5 rounded-xl transition flex items-center justify-center gap-2 text-xs shadow-lg shadow-indigo-600/20"
            >
              <span>Continuar para a Entrega</span>
              <ArrowRight class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- STEP 2: ENDEREÇO & FRETE -->
        <div v-else-if="cart.checkoutStep === 2" class="flex-1 flex flex-col justify-between overflow-hidden">
          <div class="flex-1 overflow-y-auto p-5 space-y-4 text-xs">
            <div class="flex items-center gap-2 font-bold text-neutral-200">
              <MapPin class="w-4 h-4 text-indigo-400" />
              <span>Endereço de Entrega</span>
            </div>

            <div v-if="viaCepError" class="p-2.5 bg-rose-950/40 border border-rose-800/60 rounded-xl text-rose-300">
              {{ viaCepError }}
            </div>

            <div class="space-y-1">
              <label class="font-semibold text-neutral-300">CEP</label>
              <div class="flex gap-2">
                <input 
                  v-model="address.zip_code"
                  type="text" 
                  placeholder="01310-100"
                  maxlength="9"
                  @blur="lookupViaCep"
                  @keyup.enter="lookupViaCep"
                  class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white font-mono placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden"
                />
                <button 
                  @click="lookupViaCep"
                  :disabled="isFetchingViaCep"
                  class="bg-neutral-800 hover:bg-neutral-700 text-white px-3.5 py-2 rounded-xl font-bold transition shrink-0"
                >
                  {{ isFetchingViaCep ? 'Buscando...' : 'Buscar CEP' }}
                </button>
              </div>
            </div>

            <div class="grid grid-cols-3 gap-2">
              <div class="col-span-2 space-y-1">
                <label class="font-semibold text-neutral-300">Rua / Logradouro</label>
                <input v-model="address.street" type="text" class="w-full bg-neutral-800/60 border border-neutral-700 rounded-xl px-3 py-2 text-white" />
              </div>
              <div class="space-y-1">
                <label class="font-semibold text-neutral-300">Número</label>
                <input v-model="address.number" type="text" placeholder="123" class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white focus:border-indigo-500 focus:outline-hidden" />
              </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div class="space-y-1">
                <label class="font-semibold text-neutral-300">Bairro</label>
                <input v-model="address.neighborhood" type="text" class="w-full bg-neutral-800/60 border border-neutral-700 rounded-xl px-3 py-2 text-white" />
              </div>
              <div class="space-y-1">
                <label class="font-semibold text-neutral-300">Cidade - UF</label>
                <input :value="address.city ? `${address.city} - ${address.state}` : ''" readonly class="w-full bg-neutral-800/60 border border-neutral-700 rounded-xl px-3 py-2 text-neutral-300" />
              </div>
            </div>

            <div v-if="shippingOptions.length > 0" class="space-y-2 pt-2 border-t border-neutral-800">
              <label class="font-semibold text-neutral-300 flex items-center gap-1.5">
                <Truck class="w-3.5 h-3.5 text-indigo-400" />
                <span>Opções de Envio:</span>
              </label>

              <div 
                v-for="opt in shippingOptions" 
                :key="opt.service_code"
                @click="selectedShipping = opt"
                class="p-3 rounded-xl border cursor-pointer flex items-center justify-between transition"
                :class="selectedShipping?.service_code === opt.service_code ? 'border-indigo-500 bg-indigo-950/20' : 'border-neutral-800 bg-neutral-900/40 hover:border-neutral-700'"
              >
                <div>
                  <p class="font-bold text-white">{{ opt.name }}</p>
                  <p class="text-[11px] text-neutral-400 mt-0.5">Chega em até {{ opt.days }} dias úteis</p>
                </div>
                <span class="font-bold text-white">{{ formatMoney(opt.cost_cents) }}</span>
              </div>
            </div>
          </div>

          <div class="p-5 bg-neutral-950/80 border-t border-neutral-800 flex gap-3">
            <button @click="cart.setStep(1)" class="w-1/3 bg-neutral-800 hover:bg-neutral-700 text-white font-bold py-3.5 rounded-xl transition text-xs flex items-center justify-center gap-1.5">
              <ArrowLeft class="w-4 h-4" />
              <span>Voltar</span>
            </button>
            <button 
              @click="cart.setStep(3); loadSavedCards()" 
              :disabled="!address.street || !address.number || !selectedShipping"
              class="w-2/3 bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 text-white font-bold py-3.5 rounded-xl transition text-xs flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20"
            >
              <span>Escolher Pagamento</span>
              <ArrowRight class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!-- STEP 3: PAGAMENTO (COM SELEÇÃO DE CARTÃO SALVO) -->
        <div v-else-if="cart.checkoutStep === 3" class="flex-1 flex flex-col justify-between overflow-hidden">
          <div class="flex-1 overflow-y-auto p-5 space-y-4 text-xs">
            <div class="font-bold text-neutral-200">Como você prefere pagar?</div>

            <!-- Pix -->
            <div 
              @click="paymentMethod = 'PIX'"
              class="p-4 rounded-2xl border cursor-pointer transition space-y-1.5"
              :class="paymentMethod === 'PIX' ? 'border-emerald-500 bg-emerald-950/20' : 'border-neutral-800 bg-neutral-900/40 hover:border-neutral-700'"
            >
              <div class="flex items-center justify-between">
                <span class="font-black text-white flex items-center gap-2 text-sm">
                  <QrCode class="w-4 h-4 text-emerald-400" />
                  <span>Pix</span>
                </span>
                <span class="bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-2 py-0.5 rounded text-[10px] font-extrabold uppercase">
                  10% OFF
                </span>
              </div>
              <p class="text-neutral-400 text-[11px]">Aprovação imediata e separação prioritária.</p>
              <p class="text-emerald-400 font-bold text-sm pt-1">Total: {{ formatMoney(pixTotalCents) }}</p>
            </div>

            <!-- Cartão de Crédito -->
            <div 
              @click="paymentMethod = 'CREDIT_CARD'"
              class="p-4 rounded-2xl border cursor-pointer transition space-y-3"
              :class="paymentMethod === 'CREDIT_CARD' ? 'border-indigo-500 bg-indigo-950/20' : 'border-neutral-800 bg-neutral-900/40 hover:border-neutral-700'"
            >
              <div class="flex items-center justify-between">
                <span class="font-black text-white flex items-center gap-2 text-sm">
                  <CreditCard class="w-4 h-4 text-indigo-400" />
                  <span>Cartão de Crédito</span>
                </span>
                <span class="text-neutral-400 font-bold text-xs">{{ formatMoney(finalGrandTotalCents) }}</span>
              </div>

              <div v-if="paymentMethod === 'CREDIT_CARD'" class="space-y-3 pt-2 border-t border-neutral-800/80">
                <!-- Se possui cartões salvos na conta -->
                <div v-if="savedCards.length > 0" class="space-y-2">
                  <label class="font-semibold text-neutral-300 flex items-center gap-1.5">
                    <Wallet class="w-3.5 h-3.5 text-indigo-400" />
                    <span>Selecione um cartão da sua carteira:</span>
                  </label>
                  <div class="space-y-1.5">
                    <div 
                      v-for="c in savedCards" 
                      :key="c.id"
                      @click="selectedCardId = c.id"
                      class="p-2.5 rounded-xl border flex items-center justify-between text-xs cursor-pointer transition"
                      :class="selectedCardId === c.id ? 'border-indigo-500 bg-indigo-950/30 text-white' : 'border-neutral-800 bg-neutral-950 text-neutral-300 hover:border-neutral-700'"
                    >
                      <div class="flex items-center gap-2">
                        <CreditCard class="w-4 h-4 text-indigo-400" />
                        <span>{{ c.card_brand }} final {{ c.last_four }} ({{ c.exp_month }}/{{ c.exp_year }})</span>
                      </div>
                      <span v-if="selectedCardId === c.id" class="text-indigo-400 font-bold text-xs">&check;</span>
                    </div>

                    <div 
                      @click="selectedCardId = 'NEW'"
                      class="p-2 rounded-xl border text-[11px] cursor-pointer text-center transition"
                      :class="selectedCardId === 'NEW' ? 'border-indigo-500 bg-indigo-950/20 text-white' : 'border-neutral-800 bg-neutral-950 text-neutral-400 hover:text-white'"
                    >
                      + Usar outro cartão de crédito
                    </div>
                  </div>
                </div>

                <!-- Formulário de Novo Cartão se selecionado -->
                <div v-if="savedCards.length === 0 || selectedCardId === 'NEW'" class="space-y-3 pt-1">
                  <div class="space-y-1">
                    <label class="text-[11px] font-semibold text-neutral-300">Número do Cartão</label>
                    <input 
                      v-model="creditCard.number" 
                      type="text" 
                      placeholder="0000 0000 0000 0000" 
                      maxlength="19" 
                      class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-hidden"
                    />
                  </div>

                  <div class="grid grid-cols-2 gap-2">
                    <div class="space-y-1">
                      <label class="text-[11px] font-semibold text-neutral-300">Validade (MM/AA)</label>
                      <input 
                        v-model="creditCard.expiry" 
                        type="text" 
                        placeholder="12/28" 
                        maxlength="5" 
                        class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-hidden"
                      />
                    </div>
                    <div class="space-y-1">
                      <label class="text-[11px] font-semibold text-neutral-300">CVV</label>
                      <input 
                        v-model="creditCard.cvv" 
                        type="password" 
                        placeholder="123" 
                        maxlength="4" 
                        class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-hidden"
                      />
                    </div>
                  </div>
                </div>

                <!-- Seletor de Parcelas de 1x até 12x -->
                <div class="space-y-1">
                  <label class="text-[11px] font-semibold text-neutral-300">Quantidade de Parcelas</label>
                  <select 
                    v-model="creditCard.installments"
                    class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white text-xs focus:outline-hidden"
                  >
                    <option v-for="opt in installmentOptions" :key="opt.count" :value="opt.count">
                      {{ opt.label }}
                    </option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Resumo Financeiro -->
            <div class="p-3.5 bg-neutral-950/60 border border-neutral-800 rounded-xl space-y-1.5 text-neutral-400">
              <div class="flex justify-between">
                <span>Itens:</span>
                <span>{{ cart.formattedTotal }}</span>
              </div>
              <div class="flex justify-between">
                <span>Frete ({{ selectedShipping?.name }}):</span>
                <span>{{ formatMoney(selectedShipping?.cost_cents) }}</span>
              </div>
              <div v-if="paymentMethod === 'PIX'" class="flex justify-between text-emerald-400 font-bold">
                <span>Desconto Pix (-10%):</span>
                <span>- {{ formatMoney(cart.totalCents * 0.10) }}</span>
              </div>
              <div class="flex justify-between text-white font-black text-sm pt-1 border-t border-neutral-800">
                <span>Total a Pagar:</span>
                <span>{{ formatMoney(currentPayableCents) }}</span>
              </div>
            </div>
          </div>

          <div class="p-5 bg-neutral-950/80 border-t border-neutral-800 flex gap-3">
            <button @click="cart.setStep(2)" class="w-1/3 bg-neutral-800 hover:bg-neutral-700 text-white font-bold py-3.5 rounded-xl transition text-xs flex items-center justify-center gap-1.5">
              <ArrowLeft class="w-4 h-4" />
              <span>Voltar</span>
            </button>
            <button 
              @click="handleProcessOrder"
              :disabled="isProcessingCheckout"
              class="w-2/3 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold py-3.5 rounded-xl transition text-xs flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20"
            >
              <span v-if="isProcessingCheckout">Processando Pedido...</span>
              <span v-else class="flex items-center gap-1.5">
                <span>Confirmar e Pagar</span>
                <CheckCircle2 class="w-4 h-4" />
              </span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
