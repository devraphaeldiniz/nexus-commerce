import { defineStore } from 'pinia'
import { useAuthStore } from './auth'

export const useCartStore = defineStore('cart', {
  state: () => ({
    items: JSON.parse(localStorage.getItem('nexus_cart_items') || '[]'),
    isCartOpen: false,
    checkoutStep: 1, // 1: Carrinho, 2: Endereço & Frete, 3: Pagamento, 4: Confirmação
  }),

  getters: {
    totalItemsCount: (state) => state.items.reduce((acc, item) => acc + item.quantity, 0),
    totalCents: (state) => state.items.reduce((acc, item) => acc + (item.price_cents * item.quantity), 0),
    formattedTotal: (state) => {
      const total = state.items.reduce((acc, item) => acc + (item.price_cents * item.quantity), 0)
      return (total / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })
    }
  },

  actions: {
    saveToStorage() {
      localStorage.setItem('nexus_cart_items', JSON.stringify(this.items))
    },

    addItem(product, router = null) {
      const auth = useAuthStore()

      // Regra Mercado Livre: precisa estar autenticado para comprar/adicionar ao carrinho
      if (!auth.isAuthenticated) {
        if (router) {
          router.push({ name: 'login', query: { redirect: '/', reason: 'auth_required' } })
        }
        return false
      }

      const existing = this.items.find(i => i.id === product.id)
      if (existing) {
        if (existing.quantity < product.stock_quantity) {
          existing.quantity += 1
        }
      } else {
        this.items.push({
          id: product.id,
          name: product.name,
          price_cents: product.price_cents,
          stock_quantity: product.stock_quantity,
          image_url: product.image_url,
          seller: product.seller,
          quantity: 1,
        })
      }

      this.saveToStorage()
      this.isCartOpen = true
      this.checkoutStep = 1
      return true
    },

    updateQuantity(productId, quantity) {
      const item = this.items.find(i => i.id === productId)
      if (!item) return

      if (quantity <= 0) {
        this.removeItem(productId)
      } else if (quantity <= item.stock_quantity) {
        item.quantity = quantity
        this.saveToStorage()
      }
    },

    removeItem(productId) {
      this.items = this.items.filter(i => i.id !== productId)
      this.saveToStorage()
      if (this.items.length === 0) {
        this.checkoutStep = 1
      }
    },

    clearCart() {
      this.items = []
      this.checkoutStep = 1
      this.saveToStorage()
    },

    toggleCart() {
      this.isCartOpen = !this.isCartOpen
    },

    setStep(step) {
      this.checkoutStep = step
    }
  }
})
