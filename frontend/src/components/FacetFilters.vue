<script setup>
import { 
  Filter, 
  RotateCcw, 
  Star, 
  Tag, 
  DollarSign, 
  ArrowUpDown 
} from 'lucide-vue-next'

const props = defineProps({
  categories: {
    type: Array,
    required: true,
  },
  selectedCategory: {
    type: String,
    default: '',
  },
  minPrice: {
    type: [Number, String],
    default: '',
  },
  maxPrice: {
    type: [Number, String],
    default: '',
  },
  minReputation: {
    type: [Number, String],
    default: '',
  },
  sort: {
    type: String,
    default: 'relevance',
  },
})

const emit = defineEmits([
  'update:selectedCategory',
  'update:minPrice',
  'update:maxPrice',
  'update:minReputation',
  'update:sort',
  'reset',
])
</script>

<template>
  <aside class="bg-neutral-900 border border-neutral-800 rounded-2xl p-5 space-y-6">
    <!-- Cabeçalho dos filtros -->
    <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
      <div class="flex items-center gap-2 text-white font-semibold text-xs uppercase tracking-wider">
        <Filter class="w-3.5 h-3.5 text-indigo-400" />
        <span>Filtros Facetados</span>
      </div>
      <button 
        @click="emit('reset')"
        class="text-[11px] text-neutral-400 hover:text-white flex items-center gap-1 transition"
      >
        <RotateCcw class="w-3 h-3" />
        <span>Limpar</span>
      </button>
    </div>

    <!-- Ordenação -->
    <div class="space-y-2">
      <label class="text-xs font-medium text-neutral-300 flex items-center gap-1.5">
        <ArrowUpDown class="w-3.5 h-3.5 text-neutral-500" />
        <span>Ordenar por</span>
      </label>
      <select 
        :value="sort"
        @change="emit('update:sort', $event.target.value)"
        class="w-full bg-neutral-800 border border-neutral-700 text-neutral-200 text-xs rounded-xl px-3 py-2 font-medium focus:border-indigo-500 focus:outline-hidden"
      >
        <option value="relevance">Mais Relevantes</option>
        <option value="price_asc">Menor Preço</option>
        <option value="price_desc">Maior Preço</option>
        <option value="reputation">Melhor Reputação</option>
      </select>
    </div>

    <!-- Categorias -->
    <div class="space-y-2">
      <label class="text-xs font-medium text-neutral-300 flex items-center gap-1.5">
        <Tag class="w-3.5 h-3.5 text-neutral-500" />
        <span>Categoria</span>
      </label>
      <div class="space-y-1">
        <button
          @click="emit('update:selectedCategory', '')"
          class="w-full text-left text-xs px-2.5 py-1.5 rounded-lg transition font-medium flex items-center justify-between"
          :class="selectedCategory === '' ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 font-semibold' : 'text-neutral-400 hover:bg-neutral-800 hover:text-white'"
        >
          <span>Todas as categorias</span>
        </button>
        <button
          v-for="cat in categories"
          :key="cat.slug"
          @click="emit('update:selectedCategory', cat.slug)"
          class="w-full text-left text-xs px-2.5 py-1.5 rounded-lg transition font-medium flex items-center justify-between"
          :class="selectedCategory === cat.slug ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 font-semibold' : 'text-neutral-400 hover:bg-neutral-800 hover:text-white'"
        >
          <span>{{ cat.name }}</span>
        </button>
      </div>
    </div>

    <!-- Faixa de Preço (R$) -->
    <div class="space-y-2">
      <label class="text-xs font-medium text-neutral-300 flex items-center gap-1.5">
        <DollarSign class="w-3.5 h-3.5 text-neutral-500" />
        <span>Preço (R$)</span>
      </label>
      <div class="flex items-center gap-2">
        <input 
          type="number"
          placeholder="Mín"
          :value="minPrice"
          @input="emit('update:minPrice', $event.target.value)"
          class="w-1/2 bg-neutral-800 border border-neutral-700 rounded-xl px-2.5 py-1.5 text-xs text-white placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden"
        />
        <span class="text-neutral-600 text-xs">-</span>
        <input 
          type="number"
          placeholder="Máx"
          :value="maxPrice"
          @input="emit('update:maxPrice', $event.target.value)"
          class="w-1/2 bg-neutral-800 border border-neutral-700 rounded-xl px-2.5 py-1.5 text-xs text-white placeholder-neutral-500 focus:border-indigo-500 focus:outline-hidden"
        />
      </div>
    </div>

    <!-- Reputação Mínima do Vendedor -->
    <div class="space-y-2">
      <label class="text-xs font-medium text-neutral-300 flex items-center gap-1.5">
        <Star class="w-3.5 h-3.5 text-amber-400" />
        <span>Reputação do Vendedor</span>
      </label>
      <div class="space-y-1">
        <button
          v-for="rep in [4.5, 4.0, 3.0]"
          :key="rep"
          @click="emit('update:minReputation', minReputation === rep ? '' : rep)"
          class="w-full text-left text-xs px-2.5 py-1.5 rounded-lg transition font-medium flex items-center justify-between"
          :class="minReputation === rep ? 'bg-amber-500/10 text-amber-300 border border-amber-500/30' : 'text-neutral-400 hover:bg-neutral-800 hover:text-white'"
        >
          <span class="flex items-center gap-1.5">
            <Star class="w-3 h-3 fill-amber-400 text-amber-400" />
            <span>{{ rep.toFixed(1) }} ou mais</span>
          </span>
          <span v-if="minReputation === rep" class="text-[10px] text-amber-400 font-bold">&check;</span>
        </button>
      </div>
    </div>
  </aside>
</template>
