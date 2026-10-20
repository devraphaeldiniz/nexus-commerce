import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useCartStore = defineStore('cart', () => {
  const items = ref(JSON.parse(localStorage.getItem('nexus_cart') || '[]'))
  const isOpen = ref(false)

  const saveToStorage = () => {
    localStorage.setItem('nexus_cart', JSON.stringify(items.value))
  }

  const itemCount = computed(() => {
    return items.value.reduce((acc, item) => acc + (item.quantity || 1), 0)
  })

  const subtotalCents = computed(() => {
    return items.value.reduce((acc, item) => acc + (item.price_cents * (item.quantity || 1)), 0)
  })

  const openCart = () => {
    isOpen.value = true
  }

  const closeCart = () => {
    isOpen.value = false
  }

  const toggleCart = () => {
    isOpen.value = !isOpen.value
  }

  const addItem = (product) => {
    const existing = items.value.find(i => i.id === product.id)
    if (existing) {
      existing.quantity = (existing.quantity || 1) + 1
    } else {
      items.value.push({
        id: product.id,
        name: product.name,
        price_cents: product.price_cents,
        image_url: product.image_url,
        weight_grams: product.weight_grams || 350,
        length_cm: product.length_cm || 20,
        width_cm: product.width_cm || 15,
        height_cm: product.height_cm || 10,
        quantity: 1,
      })
    }
    saveToStorage()
    isOpen.value = true // Abre o carrinho automaticamente ao adicionar item
  }

  const incrementQuantity = (id) => {
    const item = items.value.find(i => i.id === id)
    if (item) {
      item.quantity++
      saveToStorage()
    }
  }

  const decrementQuantity = (id) => {
    const index = items.value.findIndex(i => i.id === id)
    if (index !== -1) {
      if (items.value[index].quantity > 1) {
        items.value[index].quantity--
      } else {
        items.value.splice(index, 1)
      }
      saveToStorage()
    }
  }

  const removeItem = (id) => {
    items.value = items.value.filter(i => i.id !== id)
    saveToStorage()
  }

  const clearCart = () => {
    items.value = []
    saveToStorage()
  }

  return {
    items,
    isOpen,
    itemCount,
    subtotalCents,
    openCart,
    closeCart,
    toggleCart,
    addItem,
    incrementQuantity,
    decrementQuantity,
    removeItem,
    clearCart,
  }
})
