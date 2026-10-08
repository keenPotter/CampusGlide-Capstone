<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({
  modelValue: { type: [Number, String], default: '' },
  options: { type: Array, default: () => [] }, 
  placeholder: { type: String, default: '— select —' },
  icon: { type: String, default: '' }, 
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const root = ref(null)
const selected = computed(() => props.options.find((o) => o.id === props.modelValue))

function pick(o) {
  emit('update:modelValue', o.id)
  open.value = false
}

function onDocClick(e) {
  if (root.value && !root.value.contains(e.target)) open.value = false
}
onMounted(() => document.addEventListener('click', onDocClick))
onBeforeUnmount(() => document.removeEventListener('click', onDocClick))
</script>

<template>
  <div ref="root" class="cg-picker">
    <button type="button" class="cg-input cg-picker__btn" :disabled="disabled" aria-haspopup="listbox" :aria-expanded="open" @click="open = !open">
      <span v-if="selected" class="cg-picker__value">{{ selected.title }}</span>
      <span v-else class="cg-picker__placeholder">{{ placeholder }}</span>
      <Icon name="chevron" />
    </button>

    <ul v-if="open" class="cg-picker__list" role="listbox">
      <li v-if="!options.length" class="cg-picker__empty">{{ placeholder }}</li>
      <li v-for="o in options" :key="o.id" role="option" :aria-selected="o.id === modelValue">
        <button type="button" :class="['cg-picker__opt', { 'cg-picker__opt--on': o.id === modelValue }]" @click="pick(o)">
          <span class="cg-picker__title">{{ o.title }}</span>
          <span v-if="o.subtitle" class="cg-picker__sub"><Icon v-if="icon" :name="icon" /> {{ o.subtitle }}</span>
        </button>
      </li>
    </ul>
  </div>
</template>
