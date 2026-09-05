<script setup>
import { ref, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import axios from 'axios'
import { 
  Box, 
  Lock, 
  Mail, 
  AlertCircle, 
  ArrowRight, 
  ShieldCheck, 
  User, 
  CreditCard, 
  Phone, 
  Clock, 
  RefreshCw, 
  KeyRound,
  Inbox
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

// Modos: 'LOGIN' | 'REGISTER' | 'VERIFY_EMAIL' | 'TWO_FACTOR_PROMPT'
const activeMode = ref(route.query.mode === 'register' ? 'REGISTER' : 'LOGIN')

// Formulários
const loginEmail = ref('')
const loginPassword = ref('')
const totpCode = ref('')

const regForm = ref({
  name: '',
  email: '',
  cpf: '',
  phone: '',
  birth_date: '',
  password: '',
  password_confirmation: '',
})

// Verificação de E-mail
const pendingEmail = ref('')
const emailVerificationCode = ref('')
const timeLeft = ref(600) // 10 minutos em segundos
let timerInterval = null

const isLoading = ref(false)
const errorMessage = ref(route.query.expired ? 'Sua sessão expirou. Faça login novamente.' : '')
const successMessage = ref('')

const formatTimer = (seconds) => {
  const m = Math.floor(seconds / 60)
  const s = seconds % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
}

const startCountdown = () => {
  clearInterval(timerInterval)
  timeLeft.value = 600
  timerInterval = setInterval(() => {
    if (timeLeft.value > 0) {
      timeLeft.value--
    } else {
      clearInterval(timerInterval)
      errorMessage.value = 'O prazo de 10 minutos expirou. Seu pré-cadastro foi cancelado. Cadastre-se novamente.'
    }
  }, 1000)
}

onUnmounted(() => {
  clearInterval(timerInterval)
})

const redirectAfterAuth = () => {
  const redirectUrl = route.query.redirect || '/'
  if (redirectUrl !== '/' && redirectUrl !== '/login') {
    router.push(redirectUrl)
  } else {
    if (auth.isAdmin) router.push('/seller')
    else if (auth.isSeller) router.push('/seller')
    else if (auth.isLogistics) router.push('/logistics')
    else router.push('/')
  }
}

const handleLogin = async () => {
  if (!loginEmail.value || !loginPassword.value) return
  isLoading.value = true
  errorMessage.value = ''

  try {
    const { data } = await axios.post('http://localhost:8000/api/auth/login', {
      email: loginEmail.value,
      password: loginPassword.value,
      totp_code: totpCode.value || null,
    })

    if (data.requires_two_factor) {
      activeMode.value = 'TWO_FACTOR_PROMPT'
      isLoading.value = false
      return
    }

    auth.token = data.token
    auth.user = data.user
    localStorage.setItem('nexus_token', data.token)
    localStorage.setItem('nexus_user', JSON.stringify(data.user))
    axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`

    redirectAfterAuth()
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'E-mail ou senha incorretos.'
  } finally {
    isLoading.value = false
  }
}

const handleRegister = async () => {
  isLoading.value = true
  errorMessage.value = ''

  if (regForm.value.password !== regForm.value.password_confirmation) {
    errorMessage.value = 'As senhas não coincidem.'
    isLoading.value = false
    return
  }

  try {
    const { data } = await axios.post('http://localhost:8000/api/auth/register', regForm.value)
    pendingEmail.value = regForm.value.email
    activeMode.value = 'VERIFY_EMAIL'
    successMessage.value = data.message
    startCountdown()
  } catch (err) {
    const errors = err.response?.data?.errors
    errorMessage.value = errors ? Object.values(errors).flat()[0] : (err.response?.data?.message || 'Falha no cadastro.')
  } finally {
    isLoading.value = false
  }
}

const handleConfirmEmail = async () => {
  if (emailVerificationCode.value.length !== 6) return
  if (timeLeft.value <= 0) {
    errorMessage.value = 'Prazo expirado. Inicie o cadastro novamente.'
    return
  }

  isLoading.value = true
  errorMessage.value = ''

  try {
    const { data } = await axios.post('http://localhost:8000/api/auth/verify-email', {
      email: pendingEmail.value,
      code: emailVerificationCode.value,
    })

    clearInterval(timerInterval)
    auth.token = data.token
    auth.user = data.user
    localStorage.setItem('nexus_token', data.token)
    localStorage.setItem('nexus_user', JSON.stringify(data.user))
    axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`

    redirectAfterAuth()
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Código de verificação incorreto.'
    if (err.response?.status === 410) {
      clearInterval(timerInterval)
      activeMode.value = 'REGISTER'
    }
  } finally {
    isLoading.value = false
  }
}

const handleResendCode = async () => {
  isLoading.value = true
  errorMessage.value = ''
  try {
    const { data } = await axios.post('http://localhost:8000/api/auth/resend-code', {
      email: pendingEmail.value,
    })
    successMessage.value = data.message
    startCountdown()
  } catch (err) {
    errorMessage.value = err.response?.data?.message || 'Falha ao reenviar código.'
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="min-h-[85vh] flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-lg bg-neutral-900 border border-neutral-800 rounded-3xl p-8 space-y-6 shadow-2xl relative overflow-hidden">
      
      <!-- Cabeçalho -->
      <div class="text-center space-y-2">
        <div class="inline-flex p-3 bg-indigo-600/10 text-indigo-400 border border-indigo-500/20 rounded-2xl mb-1">
          <Box class="w-8 h-8" />
        </div>
        <h1 class="text-2xl font-black text-white tracking-tight">Nexus Commerce</h1>
        <p class="text-xs text-neutral-400">Segurança, pagamentos e identidade unificada</p>
      </div>

      <!-- Abas -->
      <div v-if="activeMode === 'LOGIN' || activeMode === 'REGISTER'" class="flex p-1 bg-neutral-950 border border-neutral-800 rounded-2xl">
        <button 
          type="button"
          @click="activeMode = 'LOGIN'; errorMessage = ''; successMessage = ''"
          class="flex-1 py-2 text-xs font-bold rounded-xl transition"
          :class="activeMode === 'LOGIN' ? 'bg-indigo-600 text-white shadow-md' : 'text-neutral-400 hover:text-white'"
        >
          Entrar na Conta
        </button>
        <button 
          type="button"
          @click="activeMode = 'REGISTER'; errorMessage = ''; successMessage = ''"
          class="flex-1 py-2 text-xs font-bold rounded-xl transition"
          :class="activeMode === 'REGISTER' ? 'bg-indigo-600 text-white shadow-md' : 'text-neutral-400 hover:text-white'"
        >
          Criar Nova Conta
        </button>
      </div>

      <!-- Alertas -->
      <div v-if="errorMessage" class="p-3 bg-rose-950/40 border border-rose-800/60 rounded-xl flex items-center gap-2.5 text-rose-300 text-xs">
        <AlertCircle class="w-4 h-4 shrink-0 text-rose-400" />
        <span>{{ errorMessage }}</span>
      </div>

      <div v-if="successMessage" class="p-3 bg-emerald-950/40 border border-emerald-800/60 rounded-xl flex items-center gap-2.5 text-emerald-300 text-xs">
        <Inbox class="w-4 h-4 shrink-0 text-emerald-400" />
        <span>{{ successMessage }}</span>
      </div>

      <!-- TELA DE VERIFICAÇÃO DE E-MAIL COM PRAZO DE 10 MINUTOS -->
      <div v-if="activeMode === 'VERIFY_EMAIL'" class="space-y-5 text-center">
        <div class="p-4 bg-neutral-950 border border-neutral-800 rounded-2xl text-xs space-y-2">
          <p class="font-bold text-white">Validação Obrigatória de Segurança</p>
          <p class="text-neutral-400">
            Enviamos o código de ativação para <strong class="text-indigo-300">{{ pendingEmail }}</strong>.
          </p>

          <!-- Cronômetro Regressivo de 10 minutos -->
          <div class="flex items-center justify-center gap-2 text-amber-400 font-mono font-bold text-sm pt-1">
            <Clock class="w-4 h-4" />
            <span>Tempo restante para validação: {{ formatTimer(timeLeft) }}</span>
          </div>
          <p class="text-[10px] text-rose-400">
            Se o código não for inserido antes do prazo, o pré-cadastro é cancelado e a conta não será gerada.
          </p>
        </div>

        <div>
          <input 
            v-model="emailVerificationCode" 
            type="text" 
            maxlength="6"
            placeholder="000000" 
            :disabled="timeLeft <= 0"
            class="w-full text-center text-3xl font-mono tracking-widest bg-neutral-800 border border-neutral-700 rounded-2xl py-3 text-white focus:border-indigo-500 focus:outline-hidden disabled:opacity-40"
          />
        </div>

        <button 
          @click="handleConfirmEmail"
          :disabled="emailVerificationCode.length !== 6 || isLoading || timeLeft <= 0"
          class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3.5 rounded-xl transition text-xs flex items-center justify-center gap-2 shadow-lg shadow-emerald-600/20 disabled:opacity-40"
        >
          <span v-if="isLoading">Criando e Ativando Conta...</span>
          <span v-else>Validar Código e Criar Conta</span>
        </button>

        <div class="flex items-center justify-between text-xs text-neutral-400 pt-2 border-t border-neutral-800">
          <button 
            @click="handleResendCode" 
            :disabled="isLoading"
            class="hover:text-white flex items-center gap-1 transition"
          >
            <RefreshCw class="w-3.5 h-3.5" />
            <span>Reenviar código</span>
          </button>

          <button 
            @click="activeMode = 'REGISTER'; clearInterval(timerInterval)" 
            class="hover:text-rose-400 transition"
          >
            Cancelar e recomeçar
          </button>
        </div>
      </div>

      <!-- TELA DE 2FA NO LOGIN -->
      <div v-else-if="activeMode === 'TWO_FACTOR_PROMPT'" class="space-y-4 text-center">
        <div class="p-3 bg-amber-500/10 border border-amber-500/20 rounded-2xl text-xs text-neutral-300 space-y-1">
          <KeyRound class="w-6 h-6 text-amber-400 mx-auto mb-1" />
          <p class="font-bold text-white">Autenticação em Dois Fatores (2FA)</p>
          <p class="text-neutral-400 text-[11px]">Insira o código do seu aplicativo autenticador.</p>
        </div>

        <input 
          v-model="totpCode" 
          type="text" 
          maxlength="6"
          placeholder="000000" 
          class="w-full text-center text-2xl font-mono tracking-widest bg-neutral-800 border border-neutral-700 rounded-2xl py-3 text-white focus:border-indigo-500 focus:outline-hidden"
        />

        <button 
          @click="handleLogin"
          :disabled="totpCode.length !== 6 || isLoading"
          class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3 rounded-xl transition text-xs flex items-center justify-center gap-2 shadow-lg shadow-indigo-600/20 disabled:opacity-50"
        >
          <span v-if="isLoading">Validando...</span>
          <span v-else>Confirmar e Acessar</span>
        </button>
      </div>

      <!-- FORMULÁRIO DE LOGIN -->
      <form v-else-if="activeMode === 'LOGIN'" @submit.prevent="handleLogin" class="space-y-4">
        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-neutral-300">E-mail</label>
          <div class="relative">
            <Mail class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="loginEmail" 
              type="email" 
              placeholder="seu.email@exemplo.com" 
              required
              class="w-full bg-neutral-800/80 border border-neutral-700 rounded-xl pl-10 pr-3 py-2.5 text-xs text-white placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden"
            />
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="text-xs font-semibold text-neutral-300">Senha</label>
          <div class="relative">
            <Lock class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="loginPassword" 
              type="password" 
              placeholder="••••••••" 
              required
              class="w-full bg-neutral-800/80 border border-neutral-700 rounded-xl pl-10 pr-3 py-2.5 text-xs text-white placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden"
            />
          </div>
        </div>

        <button 
          type="submit"
          :disabled="isLoading"
          class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3 rounded-xl transition flex items-center justify-center gap-2 text-xs shadow-lg shadow-indigo-600/20 disabled:opacity-50"
        >
          <span v-if="isLoading">Autenticando...</span>
          <span v-else class="flex items-center gap-1.5">
            <span>Acessar Conta</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </span>
        </button>
      </form>

      <!-- FORMULÁRIO DE CADASTRO -->
      <form v-else @submit.prevent="handleRegister" class="space-y-3.5">
        <div class="space-y-1">
          <label class="text-xs font-semibold text-neutral-300">Nome Completo</label>
          <div class="relative">
            <User class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input 
              v-model="regForm.name" 
              type="text" 
              placeholder="Ex: João da Silva Santos" 
              required
              class="w-full bg-neutral-800/80 border border-neutral-700 rounded-xl pl-10 pr-3 py-2 text-xs text-white placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="text-xs font-semibold text-neutral-300">CPF</label>
            <div class="relative">
              <CreditCard class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                v-model="regForm.cpf" 
                type="text" 
                placeholder="000.000.000-00" 
                required
                maxlength="14"
                class="w-full bg-neutral-800/80 border border-neutral-700 rounded-xl pl-10 pr-3 py-2 text-xs text-white font-mono placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden"
              />
            </div>
          </div>

          <div class="space-y-1">
            <label class="text-xs font-semibold text-neutral-300">Celular / WhatsApp</label>
            <div class="relative">
              <Phone class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                v-model="regForm.phone" 
                type="text" 
                placeholder="(11) 98765-4321" 
                required
                maxlength="15"
                class="w-full bg-neutral-800/80 border border-neutral-700 rounded-xl pl-10 pr-3 py-2 text-xs text-white font-mono placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden"
              />
            </div>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div class="col-span-2 space-y-1">
            <label class="text-xs font-semibold text-neutral-300">E-mail</label>
            <div class="relative">
              <Mail class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                v-model="regForm.email" 
                type="email" 
                placeholder="seu.email@exemplo.com" 
                required
                class="w-full bg-neutral-800/80 border border-neutral-700 rounded-xl pl-10 pr-3 py-2 text-xs text-white placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden"
              />
            </div>
          </div>

          <div class="space-y-1">
            <label class="text-xs font-semibold text-neutral-300">Nascimento</label>
            <input 
              v-model="regForm.birth_date" 
              type="date" 
              class="w-full bg-neutral-800/80 border border-neutral-700 rounded-xl px-2.5 py-2 text-xs text-white focus:border-indigo-500 focus:outline-hidden"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="text-xs font-semibold text-neutral-300">Senha</label>
            <div class="relative">
              <Lock class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                v-model="regForm.password" 
                type="password" 
                placeholder="••••••••" 
                required
                minlength="8"
                class="w-full bg-neutral-800/80 border border-neutral-700 rounded-xl pl-10 pr-3 py-2 text-xs text-white placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden"
              />
            </div>
          </div>

          <div class="space-y-1">
            <label class="text-xs font-semibold text-neutral-300">Confirmar Senha</label>
            <div class="relative">
              <Lock class="w-4 h-4 text-neutral-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input 
                v-model="regForm.password_confirmation" 
                type="password" 
                placeholder="••••••••" 
                required
                minlength="8"
                class="w-full bg-neutral-800/80 border border-neutral-700 rounded-xl pl-10 pr-3 py-2 text-xs text-white placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden"
              />
            </div>
          </div>
        </div>

        <button 
          type="submit"
          :disabled="isLoading"
          class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-3.5 rounded-xl transition flex items-center justify-center gap-2 text-xs shadow-lg shadow-emerald-600/20 disabled:opacity-50 mt-2"
        >
          <span v-if="isLoading">Processando...</span>
          <span v-else class="flex items-center gap-1.5">
            <span>Enviar Código para Meu E-mail</span>
            <ArrowRight class="w-3.5 h-3.5" />
          </span>
        </button>
      </form>
    </div>
  </div>
</template>
