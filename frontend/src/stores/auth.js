import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const normalizeUser = (newUser) => {
    if (!newUser) return null
    // A API usa timestamp; a interface também aceita o legado booleano.
    const verified = Boolean(newUser.email_verified_at || newUser.is_email_verified || newUser.email_verified)
    const safeUser = { ...newUser, is_email_verified: verified, email_verified: verified }
    // Nunca mantenha documento pessoal no cache da interface.
    delete safeUser.cpf
    delete safeUser.document
    return safeUser
  }

  // O cookie HttpOnly é a única credencial de sessão; este é apenas cache visual.
  try {
    const storedUser = localStorage.getItem('nexus_user')
    if (storedUser && storedUser !== 'undefined') user.value = normalizeUser(JSON.parse(storedUser))
  } catch (e) {
    localStorage.removeItem('nexus_user')
  }

  const setAuth = (newUser) => {
    user.value = normalizeUser(newUser)
    localStorage.setItem('nexus_user', JSON.stringify(user.value))
  }

  const logout = () => {
    user.value = null
    localStorage.removeItem('nexus_user')
  }

  return {
    user,
    setAuth,
    logout,
  }
})
