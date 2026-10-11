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
  <form class="flex flex-col gap-4" @submit.prevent="submit">
    <div class="text-center">
      <h2 class="text-page-title font-bold">Welcome back!</h2>
      <p class="mt-1 text-small text-ink-muted">
        Sign in with your NVSU account to continue.
      </p>
    </div>

    <BaseInput
      v-model="form.email"
      label="University Email"
      type="email"
      placeholder="name@nvsu.edu.ph"
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

    <BaseButton
      type="submit"
      :loading="loading"
      block
      class="mt-2 bg-primary-800! font-bold hover:bg-primary-900! active:bg-primary-900!"
    >
      Sign in
    </BaseButton>

    <p class="text-center text-small text-ink-muted">
      Need an account? Contact an administrator.
    </p>
  </form>
</template>