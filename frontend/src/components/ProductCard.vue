<script setup>
import { useRouter } from 'vue-router'
import { useCartStore } from '../stores/cart'
import { ShoppingCart, Star, ShieldCheck } from 'lucide-vue-next'

const props = defineProps({
  product: {
    type: Object,
    required: true,
  },
})

const router = useRouter()
const cart = useCartStore()

const handleAddToCart = () => {
  cart.addItem(props.product, router)
}

const formatMoney = (cents) => {
  return (cents / 100).toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
  })
}
</script>

<template>
  <div class="group bg-neutral-900 border border-neutral-800 rounded-2xl overflow-hidden flex flex-col hover:border-neutral-700 transition duration-300">
    <div class="relative aspect-4/3 overflow-hidden bg-neutral-800">
      <img 
        :src="product.image_url" 
        :alt="product.name" 
        class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
      />
      <span 
        v-if="product.category" 
        class="absolute top-3 left-3 bg-neutral-950/80 backdrop-blur-xs text-neutral-300 text-[11px] font-medium px-2.5 py-1 rounded-full border border-neutral-700/60"
      >
        {{ product.category.name }}
      </span>
      <span 
        class="absolute top-3 right-3 text-[11px] font-medium px-2 py-0.5 rounded-full"
        :class="product.stock_quantity < 5 ? 'bg-amber-950/80 text-amber-300 border border-amber-800' : 'bg-neutral-950/60 text-neutral-400'"
      >
        {{ product.stock_quantity }} restando
      </span>
    </div>

    <div class="p-5 flex-1 flex flex-col justify-between space-y-4">
      <div>
        <div v-if="product.seller" class="flex items-center justify-between gap-2 mb-2 pb-2 border-b border-neutral-800/50">
          <div class="flex items-center gap-1.5 min-w-0">
            <ShieldCheck class="w-3.5 h-3.5 text-indigo-400 shrink-0" />
            <span class="text-xs font-semibold text-neutral-300 truncate">{{ product.seller.store_name }}</span>
          </div>
          <div class="flex items-center gap-1 shrink-0 text-amber-400 text-xs font-semibold">
            <Star class="w-3.5 h-3.5 fill-amber-400" />
            <span>{{ product.seller.reputation_score }}</span>
          </div>
        </div>

        <h3 class="font-semibold text-neutral-100 group-hover:text-indigo-400 transition-colors">
          {{ product.name }}
        </h3>
        <p class="text-xs text-neutral-400 line-clamp-2 mt-1 leading-relaxed">
          {{ product.description }}
        </p>
      </div>

      <div class="flex items-center justify-between pt-2 border-t border-neutral-800/80">
        <div>
          <span class="text-[10px] text-neutral-500 uppercase tracking-wider block font-medium">Preço</span>
          <span class="text-lg font-bold text-white">{{ formatMoney(product.price_cents) }}</span>
        </div>

        <button 
          @click="handleAddToCart"
          :disabled="product.stock_quantity <= 0"
          class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-40 disabled:hover:bg-neutral-800 text-white p-2.5 rounded-xl transition flex items-center gap-1.5 text-xs font-semibold shadow-md shadow-indigo-600/20"
        >
          <ShoppingCart class="w-4 h-4" />
          <span>Comprar</span>
        </button>
      </div>
    </div>
  </div>
</template>
