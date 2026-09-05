import { defineStore } from 'pinia'
import axios from 'axios'

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: JSON.parse(localStorage.getItem('nexus_user') || 'null'),
    token: localStorage.getItem('nexus_token') || null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,
    currentRole: (state) => state.user?.role || 'GUEST',
    isAdmin: (state) => state.user?.role === 'ADMIN',
    isSeller: (state) => state.user?.role === 'SELLER',
    isLogistics: (state) => state.user?.role === 'LOGISTICS',
    isCustomer: (state) => state.user?.role === 'CUSTOMER',
    sellerId: (state) => state.user?.seller_profile?.id || null,
  },

  actions: {
    async register(formData) {
      try {
        const { data } = await axios.post('http://localhost:8000/api/auth/register', formData)
        this.token = data.token
        this.user = data.user
        localStorage.setItem('nexus_token', data.token)
        localStorage.setItem('nexus_user', JSON.stringify(data.user))
        axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
        return { success: true }
      } catch (err) {
        const errors = err.response?.data?.errors
        const message = errors ? Object.values(errors).flat()[0] : (err.response?.data?.message || 'Falha no cadastro.')
        return { success: false, message }
      }
    },

    async login(email, password) {
      try {
        const { data } = await axios.post('http://localhost:8000/api/auth/login', {
          email,
          password,
        })
        this.token = data.token
        this.user = data.user
        localStorage.setItem('nexus_token', data.token)
        localStorage.setItem('nexus_user', JSON.stringify(data.user))
        axios.defaults.headers.common['Authorization'] = `Bearer ${data.token}`
        return { success: true }
      } catch (err) {
        return {
          success: false,
          message: err.response?.data?.message || 'Falha ao realizar login.',
        }
      }
    },

    async logout() {
      try {
        if (this.token) {
          await axios.post('http://localhost:8000/api/auth/logout')
        }
      } catch (e) {
        // Ignora erro de rede no logout
      } finally {
        this.token = null
        this.user = null
        localStorage.removeItem('nexus_token')
        localStorage.removeItem('nexus_user')
        delete axios.defaults.headers.common['Authorization']
      }
    },

    initAuth() {
      if (this.token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`
      }

      axios.interceptors.response.use(
        (response) => response,
        (error) => {
          if (error.response?.status === 401 && this.token) {
            this.logout()
            window.location.href = '/login?expired=true'
          }
          return Promise.reject(error)
        }
      )
    },
  },
})
