import { defineStore } from 'pinia'
import { ref } from 'vue'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  // O cookie HttpOnly é a única credencial de sessão. O usuário local é apenas
  // cache de interface e nunca contém segredo de autenticação.
  try {
    const storedUser = localStorage.getItem('nexus_user')
    if (storedUser && storedUser !== 'undefined') user.value = JSON.parse(storedUser)
  } catch (e) {
    localStorage.removeItem('nexus_user')
  }

  const setAuth = (newUser) => {
    user.value = newUser
    token.value = null
    localStorage.setItem('nexus_user', JSON.stringify(newUser))
  }

  const logout = () => {
    user.value = null
    token.value = null
    localStorage.removeItem('nexus_user')
  }

  return {
    user,
    token,
    setAuth,
    logout,
  }
})
