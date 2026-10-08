<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage, validationErrors } from '@/lib/api'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const toast = useToast()

const form = reactive({ email: '', password: '' })
const errors = ref({})
const loading = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}

  try {
    const user = await auth.login({ ...form })
    toast.success(`Welcome back, ${user.first_name}.`)
    router.push(route.query.redirect ?? { name: 'dashboard' })
  } catch (error) {
    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) {
      toast.error(errorMessage(error, 'Unable to sign in.'))
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form
    class="flex flex-col gap-4 rounded-card border border-line bg-white p-card shadow-card"
    @submit.prevent="submit"
  >
    <div>
      <h2 class="text-section-title">Sign in</h2>
      <p class="mt-0.5 text-small text-ink-muted">Use your NVSU account credentials.</p>
    </div>

    <BaseInput
      v-model="form.email"
      label="Email"
      type="email"
      placeholder="Email"
      :error="errors.email"
      required
    />

    <BaseInput
      v-model="form.password"
      label="Password"
      type="password"
      placeholder="Password"
      :error="errors.password"
      required
    />

    <BaseButton type="submit" :loading="loading" block>Sign in</BaseButton>

    <p class="text-center text-small text-ink-muted">
      Need an account? Contact an administrator.
    </p>
  </form>
</template>