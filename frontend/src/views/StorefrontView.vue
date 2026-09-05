<script setup>
import { ref, watch, onMounted } from 'vue'
import axios from 'axios'
import ProductCard from '../components/ProductCard.vue'
import FacetFilters from '../components/FacetFilters.vue'
import { RefreshCw, PackageSearch } from 'lucide-vue-next'

const products = ref([])
const categories = ref([])
const isLoading = ref(true)
const fetchError = ref(null)

const selectedCategory = ref('')
const minPrice = ref('')
const maxPrice = ref('')
const minReputation = ref('')
const sortBy = ref('relevance')
let debounceTimer = null

const loadCategories = async () => {
  try {
    const { data } = await axios.get('http://localhost:8000/api/categories')
    categories.value = data
  } catch (err) {
    console.error('Erro ao carregar categorias:', err)
  }
}

const loadProducts = async () => {
  isLoading.value = true
  fetchError.value = null
  try {
    const params = { sort: sortBy.value }
    if (selectedCategory.value) params.category = selectedCategory.value
    if (minPrice.value) params.min_price = Math.round(parseFloat(minPrice.value) * 100)
    if (maxPrice.value) params.max_price = Math.round(parseFloat(maxPrice.value) * 100)
    if (minReputation.value) params.min_reputation = minReputation.value

    const { data } = await axios.get('http://localhost:8000/api/products', { params })
    products.value = data
  } catch (err) {
    fetchError.value = 'Não foi possível carregar os produtos.'
  } finally {
    isLoading.value = false
  }
}

watch([selectedCategory, minPrice, maxPrice, minReputation, sortBy], () => {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => loadProducts(), 350)
})

const resetFilters = () => {
  selectedCategory.value = ''
  minPrice.value = ''
  maxPrice.value = ''
  minReputation.value = ''
  sortBy.value = 'relevance'
}

onMounted(() => {
  loadCategories()
  loadProducts()
})
</script>

<template>
  <div class="space-y-6">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-extrabold tracking-tight text-white">Catálogo de Produtos</h1>
        <p class="text-neutral-400 text-xs mt-0.5">
          Encontrados <strong class="text-indigo-400">{{ products.length }}</strong> produtos certificados
        </p>
      </div>

      <button 
        @click="loadProducts"
        class="inline-flex items-center gap-1.5 text-xs text-neutral-400 hover:text-white bg-neutral-900 border border-neutral-800 px-3 py-1.5 rounded-lg transition"
      >
        <RefreshCw class="w-3 h-3" :class="{ 'animate-spin': isLoading }" />
        <span>Atualizar</span>
      </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6 items-start">
      <div class="lg:col-span-1">
        <FacetFilters 
          :categories="categories"
          v-model:selectedCategory="selectedCategory"
          v-model:minPrice="minPrice"
          v-model:maxPrice="maxPrice"
          v-model:minReputation="minReputation"
          v-model:sort="sortBy"
          @reset="resetFilters"
        />
      </div>

      <div class="lg:col-span-3">
        <div v-if="isLoading" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <div v-for="n in 3" :key="n" class="bg-neutral-900 border border-neutral-800 rounded-2xl h-80 animate-pulse" />
        </div>

        <div v-else-if="products.length === 0" class="p-16 text-center bg-neutral-900 border border-neutral-800 rounded-2xl flex flex-col items-center justify-center space-y-3">
          <div class="p-3 bg-neutral-800 text-neutral-500 rounded-2xl">
            <PackageSearch class="w-8 h-8" />
          </div>
          <h3 class="text-sm font-semibold text-white">Nenhum produto encontrado</h3>
          <button @click="resetFilters" class="mt-2 text-xs text-indigo-400 hover:text-indigo-300 font-medium">
            Limpar todos os filtros
          </button>
        </div>

        <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
          <ProductCard 
            v-for="product in products" 
            :key="product.id" 
            :product="product" 
          />
        </div>
      </div>
    </div>
  </div>
</template>
