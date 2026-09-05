<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import { useAuthStore } from '../stores/auth'
import { 
  User, 
  ShieldCheck, 
  CreditCard, 
  KeyRound, 
  Mail, 
  Lock, 
  Trash2, 
  Plus, 
  CheckCircle2, 
  AlertCircle, 
  QrCode, 
  Copy, 
  Check, 
  X,
  Phone,
  Calendar
} from 'lucide-vue-next'

const auth = useAuthStore()

// Abas: 'DATA' | 'CARDS' | 'SECURITY'
const activeTab = ref('DATA')

// Formulário de Alteração de Dados / Senha
const profileName = ref('')
const currentPassword = ref('')
const newPassword = ref('')
const newPasswordConfirm = ref('')
const isSavingProfile = ref(false)

// Cartões Salvos
const savedCards = ref([])
const isAddingCard = ref(false)
const newCard = ref({
  card_holder_name: '',
  card_number: '',
  exp_month: '',
  exp_year: '',
  card_brand: 'MASTERCARD',
})
const isSavingCard = ref(false)

// 2FA
const twoFactorSetupData = ref(null)
const twoFactorConfirmCode = ref('')
const disable2faPassword = ref('')
const isSettingUp2fa = ref(false)
const isEnabling2fa = ref(false)
const isDisabling2fa = ref(false)
const copiedSecret = ref(false)

// Feedbacks
const feedbackSuccess = ref('')
const feedbackError = ref('')

const loadProfileData = async () => {
  try {
    const { data } = await axios.get('http://localhost:8000/api/auth/me')
    auth.user = data
    profileName.value = data.name
    savedCards.value = data.saved_cards || []
  } catch (err) {
    console.error('Erro ao carregar dados do perfil:', err)
  }
}

const handleUpdateProfile = async () => {
  isSavingProfile.value = true
  feedbackError.value = ''
  feedbackSuccess.value = ''

  if (newPassword.value && newPassword.value !== newPasswordConfirm.value) {
    feedbackError.value = 'A confirmação da nova senha não confere.'
    isSavingProfile.value = false
    return
  }

  try {
    const { data } = await axios.put('http://localhost:8000/api/profile', {
      name: profileName.value,
      current_password: currentPassword.value || null,
      new_password: newPassword.value || null,
      new_password_confirmation: newPasswordConfirm.value || null,
    })

    auth.user = data.user
    feedbackSuccess.value = 'Perfil atualizado com sucesso!'
    currentPassword.value = ''
    newPassword.value = ''
    newPasswordConfirm.value = ''
  } catch (err) {
    feedbackError.value = err.response?.data?.message || 'Falha ao atualizar dados.'
  } finally {
    isSavingProfile.value = false
  }
}

const handleStoreCard = async () => {
  isSavingCard.value = true
  feedbackError.value = ''
  feedbackSuccess.value = ''

  try {
    const { data } = await axios.post('http://localhost:8000/api/profile/cards', newCard.value)
    savedCards.value.unshift(data.card)
    feedbackSuccess.value = 'Cartão salvo com sucesso para compras futuras!'
    isAddingCard.value = false
    newCard.value = { card_holder_name: '', card_number: '', exp_month: '', exp_year: '', card_brand: 'MASTERCARD' }
  } catch (err) {
    feedbackError.value = err.response?.data?.message || 'Erro ao cadastrar cartão.'
  } finally {
    isSavingCard.value = false
  }
}

const handleDeleteCard = async (cardId) => {
  if (!confirm('Deseja realmente remover este cartão salvo?')) return
  try {
    await axios.delete(`http://localhost:8000/api/profile/cards/${cardId}`)
    savedCards.value = savedCards.value.filter(c => c.id !== cardId)
    feedbackSuccess.value = 'Cartão removido com sucesso.'
  } catch (err) {
    feedbackError.value = 'Erro ao remover cartão.'
  }
}

const start2faSetup = async () => {
  isSettingUp2fa.value = true
  feedbackError.value = ''
  try {
    const { data } = await axios.post('http://localhost:8000/api/profile/2fa/setup')
    twoFactorSetupData.value = data
  } catch (err) {
    feedbackError.value = 'Falha ao gerar segredo 2FA.'
  } finally {
    isSettingUp2fa.value = false
  }
}

const confirmEnable2fa = async () => {
  if (twoFactorConfirmCode.value.length !== 6) return
  isEnabling2fa.value = true
  feedbackError.value = ''

  try {
    const { data } = await axios.post('http://localhost:8000/api/profile/2fa/enable', {
      code: twoFactorConfirmCode.value,
    })
    auth.user.two_factor_enabled = true
    feedbackSuccess.value = data.message
    twoFactorSetupData.value = null
    twoFactorConfirmCode.value = ''
  } catch (err) {
    feedbackError.value = err.response?.data?.message || 'Código 2FA incorreto.'
  } finally {
    isEnabling2fa.value = false
  }
}

const handleDisable2fa = async () => {
  if (!disable2faPassword.value) return
  isDisabling2fa.value = true
  feedbackError.value = ''

  try {
    const { data } = await axios.post('http://localhost:8000/api/profile/2fa/disable', {
      password: disable2faPassword.value,
    })
    auth.user.two_factor_enabled = false
    feedbackSuccess.value = data.message
    disable2faPassword.value = ''
  } catch (err) {
    feedbackError.value = err.response?.data?.message || 'Senha incorreta para desativar 2FA.'
  } finally {
    isDisabling2fa.value = false
  }
}

const copySecret = (text) => {
  navigator.clipboard.writeText(text)
  copiedSecret.value = true
  setTimeout(() => copiedSecret.value = false, 2500)
}

onMounted(() => {
  loadProfileData()
})
</script>

<template>
  <div class="max-w-4xl mx-auto space-y-6">
    <!-- Header do Perfil -->
    <div class="bg-neutral-900 border border-neutral-800 rounded-3xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-2xl bg-indigo-600/20 text-indigo-400 border border-indigo-500/30 flex items-center justify-center font-black text-xl">
          {{ auth.user?.name?.charAt(0) || 'U' }}
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-black text-white">{{ auth.user?.name }}</h2>
            <span 
              class="text-[10px] font-black px-2 py-0.5 rounded uppercase"
              :class="{
                'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30': auth.isSeller,
                'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30': auth.isCustomer,
                'bg-amber-500/20 text-amber-300 border border-amber-500/30': auth.isLogistics,
                'bg-rose-500/20 text-rose-300 border border-rose-500/30': auth.isAdmin
              }"
            >
              {{ auth.currentRole }}
            </span>
          </div>
          <p class="text-xs text-neutral-400 mt-0.5">{{ auth.user?.email }}</p>
        </div>
      </div>

      <!-- Selo de Verificação de E-mail -->
      <div class="flex items-center gap-2 bg-neutral-950/60 border border-neutral-800 px-3.5 py-2 rounded-2xl">
        <div 
          class="w-2.5 h-2.5 rounded-full" 
          :class="auth.user?.email_verified ? 'bg-emerald-400 animate-pulse' : 'bg-amber-400'"
        />
        <span class="text-xs font-semibold text-neutral-300">
          {{ auth.user?.email_verified ? 'E-mail Verificado' : 'E-mail Pendente de Confirmação' }}
        </span>
      </div>
    </div>

    <!-- Navegação por Abas -->
    <div class="flex p-1.5 bg-neutral-900 border border-neutral-800 rounded-2xl gap-1">
      <button 
        @click="activeTab = 'DATA'; feedbackError = ''; feedbackSuccess = ''"
        class="flex-1 py-2.5 text-xs font-bold rounded-xl transition flex items-center justify-center gap-2"
        :class="activeTab === 'DATA' ? 'bg-indigo-600 text-white shadow-md' : 'text-neutral-400 hover:text-white'"
      >
        <User class="w-4 h-4" />
        <span>Meus Dados & Senha</span>
      </button>

      <button 
        @click="activeTab = 'CARDS'; feedbackError = ''; feedbackSuccess = ''"
        class="flex-1 py-2.5 text-xs font-bold rounded-xl transition flex items-center justify-center gap-2"
        :class="activeTab === 'CARDS' ? 'bg-indigo-600 text-white shadow-md' : 'text-neutral-400 hover:text-white'"
      >
        <CreditCard class="w-4 h-4" />
        <span>Cartões Cadastrados</span>
      </button>

      <button 
        @click="activeTab = 'SECURITY'; feedbackError = ''; feedbackSuccess = ''"
        class="flex-1 py-2.5 text-xs font-bold rounded-xl transition flex items-center justify-center gap-2"
        :class="activeTab === 'SECURITY' ? 'bg-indigo-600 text-white shadow-md' : 'text-neutral-400 hover:text-white'"
      >
        <KeyRound class="w-4 h-4" />
        <span>Segurança & 2FA</span>
      </button>
    </div>

    <!-- Feedbacks Globais -->
    <div v-if="feedbackSuccess" class="p-3 bg-emerald-950/40 border border-emerald-800/60 rounded-xl flex items-center gap-2.5 text-emerald-300 text-xs">
      <CheckCircle2 class="w-4 h-4 shrink-0 text-emerald-400" />
      <span>{{ feedbackSuccess }}</span>
    </div>

    <div v-if="feedbackError" class="p-3 bg-rose-950/40 border border-rose-800/60 rounded-xl flex items-center gap-2.5 text-rose-300 text-xs">
      <AlertCircle class="w-4 h-4 shrink-0 text-rose-400" />
      <span>{{ feedbackError }}</span>
    </div>

    <!-- ABA 1: MEUS DADOS & SENHA -->
    <div v-if="activeTab === 'DATA'" class="bg-neutral-900 border border-neutral-800 rounded-3xl p-6 space-y-6">
      <h3 class="text-sm font-bold text-white border-b border-neutral-800 pb-3">Informações Cadastrais & Acesso</h3>

      <form @submit.prevent="handleUpdateProfile" class="space-y-4 text-xs">
        <div class="space-y-1">
          <label class="font-semibold text-neutral-300">Nome de Exibição</label>
          <input 
            v-model="profileName" 
            type="text" 
            required 
            class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white focus:border-indigo-500 focus:outline-hidden"
          />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="space-y-1">
            <label class="font-semibold text-neutral-400">E-mail (Não editável diretamente)</label>
            <input 
              :value="auth.user?.email" 
              readonly 
              class="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3 py-2 text-neutral-400 cursor-not-allowed"
            />
          </div>

          <div class="space-y-1">
            <label class="font-semibold text-neutral-400">Documento CPF</label>
            <input 
              :value="auth.user?.customer_profile?.cpf || 'Não informado'" 
              readonly 
              class="w-full bg-neutral-950 border border-neutral-800 rounded-xl px-3 py-2 text-neutral-400 font-mono cursor-not-allowed"
            />
          </div>
        </div>

        <!-- Alteração de Senha -->
        <div class="pt-4 border-t border-neutral-800/80 space-y-3">
          <h4 class="font-bold text-white text-xs">Alterar Senha de Acesso</h4>
          <p class="text-neutral-400 text-[11px]">Deixe em branco caso não queira alterar sua senha atual.</p>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="space-y-1">
              <label class="font-semibold text-neutral-300">Senha Atual</label>
              <input 
                v-model="currentPassword" 
                type="password" 
                placeholder="••••••••" 
                class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white focus:border-indigo-500 focus:outline-hidden"
              />
            </div>
            <div class="space-y-1">
              <label class="font-semibold text-neutral-300">Nova Senha</label>
              <input 
                v-model="newPassword" 
                type="password" 
                placeholder="••••••••" 
                class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white focus:border-indigo-500 focus:outline-hidden"
              />
            </div>
            <div class="space-y-1">
              <label class="font-semibold text-neutral-300">Confirmar Nova Senha</label>
              <input 
                v-model="newPasswordConfirm" 
                type="password" 
                placeholder="••••••••" 
                class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white focus:border-indigo-500 focus:outline-hidden"
              />
            </div>
          </div>
        </div>

        <button 
          type="submit"
          :disabled="isSavingProfile"
          class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 px-6 rounded-xl transition shadow-md shadow-indigo-600/20 disabled:opacity-50"
        >
          {{ isSavingProfile ? 'Salvando Alterações...' : 'Salvar Alterações' }}
        </button>
      </form>
    </div>

    <!-- ABA 2: CARTÕES CADASTRADOS (WALLET PCI COMPLIANT) -->
    <div v-else-if="activeTab === 'CARDS'" class="bg-neutral-900 border border-neutral-800 rounded-3xl p-6 space-y-6">
      <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
        <div>
          <h3 class="text-sm font-bold text-white">Cartões Salvos</h3>
          <p class="text-xs text-neutral-400">Pague suas compras com 1 clique usando seus cartões salvos.</p>
        </div>
        <button 
          @click="isAddingCard = !isAddingCard"
          class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-3 py-1.5 rounded-xl transition flex items-center gap-1.5 shadow-md shadow-indigo-600/20"
        >
          <Plus class="w-3.5 h-3.5" />
          <span>{{ isAddingCard ? 'Cancelar' : 'Adicionar Cartão' }}</span>
        </button>
      </div>

      <!-- Formulário de Cadastro de Novo Cartão -->
      <div v-if="isAddingCard" class="bg-neutral-950/80 border border-neutral-800 rounded-2xl p-5 space-y-4 text-xs">
        <h4 class="font-bold text-white">Novo Cartão de Crédito</h4>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <div class="space-y-1">
            <label class="font-semibold text-neutral-300">Nome Impresso no Cartão</label>
            <input 
              v-model="newCard.card_holder_name" 
              type="text" 
              placeholder="Ex: RAPHAEL DINIZ" 
              class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white uppercase focus:border-indigo-500 focus:outline-hidden"
            />
          </div>

          <div class="space-y-1">
            <label class="font-semibold text-neutral-300">Bandeira</label>
            <select 
              v-model="newCard.card_brand" 
              class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white focus:outline-hidden"
            >
              <option value="MASTERCARD">Mastercard</option>
              <option value="VISA">Visa</option>
              <option value="ELO">Elo</option>
              <option value="AMEX">American Express</option>
              <option value="HIPERCARD">Hipercard</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-3 gap-3">
          <div class="col-span-2 space-y-1">
            <label class="font-semibold text-neutral-300">Número do Cartão</label>
            <input 
              v-model="newCard.card_number" 
              type="text" 
              placeholder="0000 0000 0000 0000" 
              maxlength="19" 
              class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white font-mono focus:outline-hidden"
            />
          </div>

          <div class="grid grid-cols-2 gap-1.5">
            <div class="space-y-1">
              <label class="font-semibold text-neutral-300">Mês</label>
              <input 
                v-model="newCard.exp_month" 
                type="text" 
                placeholder="12" 
                maxlength="2" 
                class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-2 py-2 text-white font-mono text-center focus:outline-hidden"
              />
            </div>
            <div class="space-y-1">
              <label class="font-semibold text-neutral-300">Ano</label>
              <input 
                v-model="newCard.exp_year" 
                type="text" 
                placeholder="28" 
                maxlength="4" 
                class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-2 py-2 text-white font-mono text-center focus:outline-hidden"
              />
            </div>
          </div>
        </div>

        <button 
          @click="handleStoreCard"
          :disabled="isSavingCard || !newCard.card_holder_name || !newCard.card_number"
          class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold py-2.5 px-5 rounded-xl transition disabled:opacity-50"
        >
          {{ isSavingCard ? 'Salvando Cartão...' : 'Salvar Cartão com Segurança' }}
        </button>
      </div>

      <!-- Lista de Cartões -->
      <div v-if="savedCards.length === 0" class="text-center py-10 text-neutral-500 text-xs">
        Você ainda não possui cartões salvos na sua carteira.
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div 
          v-for="card in savedCards" 
          :key="card.id"
          class="p-4 bg-neutral-950 border border-neutral-800 rounded-2xl flex items-center justify-between hover:border-neutral-700 transition"
        >
          <div class="flex items-center gap-3">
            <div class="p-2.5 bg-neutral-900 border border-neutral-800 rounded-xl text-indigo-400">
              <CreditCard class="w-6 h-6" />
            </div>
            <div>
              <p class="text-xs font-bold text-white flex items-center gap-1.5">
                <span>{{ card.card_brand }} final {{ card.last_four }}</span>
                <span v-if="card.is_default" class="bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 text-[9px] px-1.5 py-0.2 rounded font-extrabold uppercase">
                  Padrão
                </span>
              </p>
              <p class="text-[11px] text-neutral-400 mt-0.5">Expira em: {{ card.exp_month }}/{{ card.exp_year }}</p>
              <p class="text-[10px] text-neutral-500 uppercase">{{ card.card_holder_name }}</p>
            </div>
          </div>

          <button 
            @click="handleDeleteCard(card.id)"
            class="p-2 text-neutral-500 hover:text-rose-400 hover:bg-neutral-900 rounded-xl transition"
            title="Excluir Cartão"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </div>
    </div>

    <!-- ABA 3: SEGURANÇA & 2FA -->
    <div v-else-if="activeTab === 'SECURITY'" class="bg-neutral-900 border border-neutral-800 rounded-3xl p-6 space-y-6">
      <div class="border-b border-neutral-800 pb-3">
        <h3 class="text-sm font-bold text-white">Autenticação em Dois Fatores (2FA)</h3>
        <p class="text-xs text-neutral-400">Proteja sua conta contra acessos não autorizados usando um app autenticador.</p>
      </div>

      <!-- Status Atual do 2FA -->
      <div 
        class="p-4 rounded-2xl border flex items-center justify-between text-xs"
        :class="auth.user?.two_factor_enabled ? 'bg-emerald-950/20 border-emerald-800/50' : 'bg-neutral-950 border-neutral-800'"
      >
        <div class="flex items-center gap-3">
          <ShieldCheck v-if="auth.user?.two_factor_enabled" class="w-6 h-6 text-emerald-400" />
          <AlertCircle v-else class="w-6 h-6 text-amber-400" />
          <div>
            <p class="font-bold text-white">
              {{ auth.user?.two_factor_enabled ? 'O 2FA está ativado na sua conta' : 'O 2FA está desativado' }}
            </p>
            <p class="text-neutral-400 text-[11px]">
              {{ auth.user?.two_factor_enabled ? 'Ao fazer login, você precisará informar o código de 6 dígitos gerado no seu celular.' : 'Recomendamos ativar para evitar que terceiros acessem suas informações.' }}
            </p>
          </div>
        </div>

        <button 
          v-if="!auth.user?.two_factor_enabled && !twoFactorSetupData"
          @click="start2faSetup"
          :disabled="isSettingUp2fa"
          class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-4 py-2 rounded-xl transition shrink-0"
        >
          {{ isSettingUp2fa ? 'Gerando Chave...' : 'Ativar 2FA' }}
        </button>
      </div>

      <!-- Fluxo de Ativação do 2FA (QR Code e Código) -->
      <div v-if="twoFactorSetupData" class="bg-neutral-950 border border-neutral-800 rounded-2xl p-5 space-y-5 text-xs">
        <div class="flex items-center justify-between border-b border-neutral-800 pb-2">
          <h4 class="font-bold text-white">Escaneie o QR Code no seu aplicativo</h4>
          <button @click="twoFactorSetupData = null" class="text-neutral-400 hover:text-white">
            <X class="w-4 h-4" />
          </button>
        </div>

        <div class="flex flex-col sm:flex-row items-center gap-6">
          <div class="p-3 bg-white rounded-2xl shadow-lg shrink-0">
            <img :src="twoFactorSetupData.qr_code_url" alt="QR Code 2FA" class="w-36 h-36" />
          </div>

          <div class="space-y-2 flex-1 text-left">
            <p class="text-neutral-300">
              1. Abra seu aplicativo (Google Authenticator, Authy ou Microsoft Authenticator).<br/>
              2. Escaneie a imagem ao lado ou insira manualmente a chave secreta:
            </p>
            <div class="flex items-center gap-2">
              <span class="font-mono bg-neutral-900 border border-neutral-800 px-3 py-1.5 rounded-lg text-indigo-400 font-bold select-all">
                {{ twoFactorSetupData.secret }}
              </span>
              <button 
                @click="copySecret(twoFactorSetupData.secret)"
                class="p-1.5 bg-neutral-800 hover:bg-neutral-700 text-neutral-300 rounded-lg transition"
                title="Copiar Chave"
              >
                <Check v-if="copiedSecret" class="w-3.5 h-3.5 text-emerald-400" />
                <Copy v-else class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>

        <!-- Códigos de Recuperação -->
        <div class="p-3 bg-neutral-900/60 border border-neutral-800 rounded-xl space-y-2">
          <p class="font-bold text-neutral-200">Códigos de Recuperação (Guarde em local seguro):</p>
          <div class="grid grid-cols-3 gap-2 font-mono text-[11px] text-neutral-400">
            <span v-for="c in twoFactorSetupData.recovery_codes" :key="c" class="bg-neutral-950 p-1.5 rounded text-center">
              {{ c }}
            </span>
          </div>
        </div>

        <!-- Confirmação do Código -->
        <div class="space-y-2 pt-2 border-t border-neutral-800">
          <label class="font-semibold text-neutral-300">Digite o código de 6 dígitos gerado no app para confirmar:</label>
          <div class="flex gap-2">
            <input 
              v-model="twoFactorConfirmCode" 
              type="text" 
              maxlength="6" 
              placeholder="000000" 
              class="w-48 text-center tracking-widest font-mono text-base bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-1.5 text-white focus:outline-hidden"
            />
            <button 
              @click="confirmEnable2fa"
              :disabled="twoFactorConfirmCode.length !== 6 || isEnabling2fa"
              class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-4 py-1.5 rounded-xl transition disabled:opacity-50"
            >
              {{ isEnabling2fa ? 'Confirmando...' : 'Confirmar e Ativar 2FA' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Desativação de 2FA -->
      <div v-if="auth.user?.two_factor_enabled" class="bg-neutral-950/60 border border-neutral-800 rounded-2xl p-5 space-y-3 text-xs">
        <h4 class="font-bold text-white">Desativar Autenticação em Dois Fatores</h4>
        <p class="text-neutral-400">Para desativar, confirme sua senha de acesso:</p>
        <div class="flex gap-2 max-w-md">
          <input 
            v-model="disable2faPassword" 
            type="password" 
            placeholder="Sua senha atual" 
            class="w-full bg-neutral-800 border border-neutral-700 rounded-xl px-3 py-2 text-white focus:outline-hidden"
          />
          <button 
            @click="handleDisable2fa"
            :disabled="!disable2faPassword || isDisabling2fa"
            class="bg-rose-600/20 hover:bg-rose-600 text-rose-300 hover:text-white border border-rose-500/30 font-bold px-4 py-2 rounded-xl transition shrink-0 disabled:opacity-50"
          >
            {{ isDisabling2fa ? 'Desativando...' : 'Desativar 2FA' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
