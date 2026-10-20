<script setup>
import { ref } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { 
  X, 
  ShieldCheck, 
  Mail, 
  Lock, 
  User, 
  Store, 
  ShoppingBag, 
  FileText, 
  KeyRound,
  Loader2 
} from 'lucide-vue-next'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'https://pleasant-light-production.up.railway.app/api'

const props = defineProps({
  isOpen: Boolean
})
const emit = defineEmits(['close'])

const router = useRouter()
const auth = useAuthStore()

// Telas do Modal: 'form' ou 'otp'
const step = ref('form')

const isRegister = ref(false)
const selectedRole = ref('CUSTOMER') // 'CUSTOMER' ou 'SELLER'
const name = ref('')
const email = ref('')
const password = ref('')
const storeName = ref('')
const documentNumber = ref('')

// OTP
const otpEmail = ref('')
const otpCode = ref('')
const otpSuccess = ref('')

const isLoading = ref(false)
const errorMessage = ref('')

const resetForm = () => {
  step.value = 'form'
  name.value = ''
  email.value = ''
  password.value = ''
  storeName.value = ''
  documentNumber.value = ''
  selectedRole.value = 'CUSTOMER'
  otpEmail.value = ''
  otpCode.value = ''
  otpSuccess.value = ''
  errorMessage.value = ''
}

const handleSubmit = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const endpoint = isRegister.value 
      ? `${API_BASE_URL}/auth/register` 
      : `${API_BASE_URL}/auth/login`

    let payload = {}

    if (isRegister.value) {
      payload = {
        name: name.value,
        email: email.value,
        password: password.value,
        role: selectedRole.value,
        document: documentNumber.value,
        store_name: selectedRole.value === 'SELLER' ? storeName.value : null
      }
    } else {
      payload = {
        email: email.value,
        password: password.value,
      }
    }

    const res = await axios.post(endpoint, payload)
    const data = res.data

    if (data.requires_verification) {
      otpEmail.value = data.email || email.value
      otpCode.value = data.debug_code || ''
      otpSuccess.value = data.message || 'Digite o código de 6 dígitos enviado ao seu e-mail.'
      step.value = 'otp'
      return
    }

    // Login sem OTP direto (se houvesse)
    auth.setAuth(data.user, data.token)
    emit('close')
    resetForm()

    if (data.user?.role === 'SELLER') {
      router.push('/seller')
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Falha na autenticação. Verifique os dados informados.'
  } finally {
    isLoading.value = false
  }
}

const handleVerifyOtp = async () => {
  isLoading.value = true
  errorMessage.value = ''

  try {
    const { data } = await axios.post(`${API_BASE_URL}/auth/verify-email`, {
      email: otpEmail.value,
      code: otpCode.value
    })

    auth.setAuth(data.user, data.token)
    emit('close')
    resetForm()

    if (data.user?.role === 'SELLER') {
      router.push('/seller')
    }
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Código incorreto ou expirado.'
  } finally {
    isLoading.value = false
  }
}

const handleResendOtp = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const { data } = await axios.post(`${API_BASE_URL}/auth/resend-code`, {
      email: otpEmail.value
    })
    if (data.debug_code) otpCode.value = data.debug_code
    otpSuccess.value = 'Novo código gerado e enviado!'
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Falha ao reenviar código.'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div v-if="isOpen" class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4">
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-black/80 backdrop-blur-xs transition-opacity" @click="emit('close')" />

    <!-- Card Modal -->
    <div class="relative bg-neutral-900 border border-neutral-800 rounded-3xl w-full max-w-md p-6 sm:p-8 shadow-2xl text-white space-y-6 z-10">
      
      <!-- Topo -->
      <div class="flex items-center justify-between border-b border-neutral-800/80 pb-4">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-400 flex items-center justify-center">
            <ShieldCheck class="w-5 h-5" />
          </div>
          <div>
            <h3 class="text-sm font-black">
              {{ step === 'otp' ? 'Verificação de Segurança' : (isRegister ? 'Criar Conta no Nexus' : 'Acessar Plataforma') }}
            </h3>
            <p class="text-[11px] text-neutral-400">
              {{ step === 'otp' ? 'Confirme o código de 6 dígitos' : (isRegister ? 'Cadastro seguro de cliente ou fornecedor' : 'Informe seu e-mail e senha') }}
            </p>
          </div>
        </div>

        <button 
          @click="emit('close')" 
          class="p-2 text-neutral-400 hover:text-white hover:bg-neutral-800 rounded-xl transition cursor-pointer"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Erros e Sucesso -->
      <div v-if="errorMessage" class="p-3 bg-rose-950/40 border border-rose-900 rounded-xl text-xs text-rose-300">
        {{ errorMessage }}
      </div>
      <div v-if="otpSuccess && step === 'otp'" class="p-3 bg-emerald-950/40 border border-emerald-900 rounded-xl text-xs text-emerald-300">
        {{ otpSuccess }}
      </div>

      <!-- ETAPA 1: FORMULÁRIO DE LOGIN OU CADASTRO -->
      <form v-if="step === 'form'" @submit.prevent="handleSubmit" class="space-y-4">
        
        <!-- Seletor Comprador / Vendedor (Apenas Cadastro) -->
        <div v-if="isRegister" class="space-y-1.5">
          <label class="text-[11px] font-semibold text-neutral-300">Tipo de Conta *</label>
          <div class="grid grid-cols-2 gap-2">
            <button 
              type="button" 
              @click="selectedRole = 'CUSTOMER'" 
              class="p-3 rounded-2xl border transition flex flex-col items-center gap-1.5 cursor-pointer text-center"
              :class="selectedRole === 'CUSTOMER' ? 'bg-indigo-600/15 border-indigo-500 text-white' : 'bg-neutral-950 border-neutral-800 text-neutral-400 hover:text-neutral-200'"
            >
              <ShoppingBag class="w-5 h-5" :class="selectedRole === 'CUSTOMER' ? 'text-indigo-400' : 'text-neutral-500'" />
              <div>
                <p class="text-xs font-bold leading-tight">Comprador</p>
                <p class="text-[10px] text-neutral-500 leading-tight">Usa CPF</p>
              </div>
            </button>

            <button 
              type="button" 
              @click="selectedRole = 'SELLER'" 
              class="p-3 rounded-2xl border transition flex flex-col items-center gap-1.5 cursor-pointer text-center"
              :class="selectedRole === 'SELLER' ? 'bg-indigo-600/15 border-indigo-500 text-white' : 'bg-neutral-950 border-neutral-800 text-neutral-400 hover:text-neutral-200'"
            >
              <Store class="w-5 h-5" :class="selectedRole === 'SELLER' ? 'text-indigo-400' : 'text-neutral-500'" />
              <div>
                <p class="text-xs font-bold leading-tight">Fornecedor</p>
                <p class="text-[10px] text-neutral-500 leading-tight">Usa CNPJ</p>
              </div>
            </button>
          </div>
        </div>

        <!-- Nome / Razão Social -->
        <div v-if="isRegister" class="space-y-1">
          <label class="text-[11px] font-semibold text-neutral-300">
            {{ selectedRole === 'SELLER' ? 'Razão Social / Nome da Empresa *' : 'Nome Completo *' }}
          </label>
          <div class="relative">
            <User class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="name" 
              type="text" 
              :placeholder="selectedRole === 'SELLER' ? 'Ex: Nexus Distribuidora LTDA' : 'Ex: Carlos Eduardo'" 
              required 
              class="w-full bg-neutral-950 border border-neutral-800 focus:border-indigo-500 rounded-xl pl-10 pr-3.5 py-2.5 text-xs text-white placeholder-neutral-500 focus:outline-hidden"
            />
          </div>
        </div>

        <!-- Campo Dinâmico: CPF para Comprador / CNPJ para Vendedor -->
        <div v-if="isRegister" class="space-y-1">
          <label class="text-[11px] font-semibold text-neutral-300">
            {{ selectedRole === 'SELLER' ? 'CNPJ do Fornecedor *' : 'CPF do Usuário *' }}
          </label>
          <div class="relative">
            <FileText class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="documentNumber" 
              type="text" 
              :placeholder="selectedRole === 'SELLER' ? '00.000.000/0001-00' : '000.000.000-00'" 
              required 
              class="w-full bg-neutral-950 border border-neutral-800 focus:border-indigo-500 rounded-xl pl-10 pr-3.5 py-2.5 text-xs text-white placeholder-neutral-500 focus:outline-hidden font-mono"
            />
          </div>
        </div>

        <!-- Nome Fantasia (Apenas Vendedor) -->
        <div v-if="isRegister && selectedRole === 'SELLER'" class="space-y-1">
          <label class="text-[11px] font-semibold text-indigo-400">Nome Fantasia da Loja *</label>
          <div class="relative">
            <Store class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="storeName" 
              type="text" 
              placeholder="Ex: Nexus Hardware & Tech" 
              required 
              class="w-full bg-neutral-950 border border-neutral-800 focus:border-indigo-500 rounded-xl pl-10 pr-3.5 py-2.5 text-xs text-white placeholder-neutral-500 focus:outline-hidden"
            />
          </div>
        </div>

        <!-- E-mail -->
        <div class="space-y-1">
          <label class="text-[11px] font-semibold text-neutral-300">E-mail *</label>
          <div class="relative">
            <Mail class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="email" 
              type="email" 
              placeholder="seu.email@exemplo.com" 
              required 
              class="w-full bg-neutral-950 border border-neutral-800 focus:border-indigo-500 rounded-xl pl-10 pr-3.5 py-2.5 text-xs text-white placeholder-neutral-500 focus:outline-hidden"
            />
          </div>
        </div>

        <!-- Senha -->
        <div class="space-y-1">
          <label class="text-[11px] font-semibold text-neutral-300">Senha *</label>
          <div class="relative">
            <Lock class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="password" 
              type="password" 
              placeholder="••••••••" 
              required 
              minlength="6"
              class="w-full bg-neutral-950 border border-neutral-800 focus:border-indigo-500 rounded-xl pl-10 pr-3.5 py-2.5 text-xs text-white placeholder-neutral-500 focus:outline-hidden"
            />
          </div>
        </div>

        <!-- Botão Submit -->
        <button 
          type="submit" 
          :disabled="isLoading" 
          class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20 disabled:opacity-50 cursor-pointer"
        >
          <Loader2 v-if="isLoading" class="w-4 h-4 animate-spin" />
          <span v-else>{{ isRegister ? 'Cadastrar e Enviar Código' : 'Entrar (Autenticação 2FA)' }}</span>
        </button>
      </form>

      <!-- ETAPA 2: DIGITAR CÓDIGO OTP -->
      <form v-else @submit.prevent="handleVerifyOtp" class="space-y-4 text-center">
        <p class="text-xs text-neutral-400">
          Código de 6 dígitos enviado para <br/>
          <span class="text-indigo-400 font-bold font-mono">{{ otpEmail }}</span>
        </p>

        <div class="relative max-w-[200px] mx-auto">
          <input 
            v-model="otpCode" 
            type="text" 
            maxlength="6" 
            required 
            placeholder="000000"
            class="w-full text-center text-2xl font-mono tracking-[0.35em] bg-neutral-950 border border-neutral-700 rounded-xl py-2.5 text-indigo-400 font-bold focus:border-indigo-500 focus:outline-hidden"
          />
        </div>

        <button 
          type="submit" 
          :disabled="isLoading || otpCode.length !== 6" 
          class="w-full py-3 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition flex items-center justify-center gap-2 disabled:opacity-50 cursor-pointer"
        >
          <Loader2 v-if="isLoading" class="w-4 h-4 animate-spin" />
          <span v-else>Confirmar Código de Acesso</span>
        </button>

        <div class="flex items-center justify-between pt-2 text-xs">
          <button type="button" @click="handleResendOtp" class="text-indigo-400 hover:underline cursor-pointer">
            Reenviar código
          </button>
          <button type="button" @click="step = 'form'" class="text-neutral-500 hover:text-white cursor-pointer">
            Voltar
          </button>
        </div>
      </form>

      <!-- Alternador Login / Cadastro -->
      <div v-if="step === 'form'" class="text-center pt-3 border-t border-neutral-800/80">
        <button 
          type="button" 
          @click="isRegister = !isRegister; errorMessage = ''" 
          class="text-xs text-neutral-400 hover:text-indigo-400 transition cursor-pointer"
        >
          {{ isRegister ? 'Já possui conta? Clique para Entrar' : 'Não tem cadastro? Crie sua conta aqui' }}
        </button>
      </div>

    </div>
  </div>
</template>
