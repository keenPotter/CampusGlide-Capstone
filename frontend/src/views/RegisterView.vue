<script setup>
import { reactive, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { errorMessage, validationErrors } from '@/lib/api'
import { useToast } from '@/composables/useToast'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'

const auth = useAuthStore()
const router = useRouter()
const toast = useToast()

const form = reactive({
  first_name: '',
  last_name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: '',
})

const roleOptions = [
  { value: 'faculty', label: 'Faculty' },
  { value: 'driver', label: 'Driver' },
  { value: 'guard', label: 'Guard' },
  { value: 'administrator', label: 'Administrator' },
]

const errors = ref({})
const loading = ref(false)

async function submit() {
  loading.value = true
  errors.value = {}

  try {
    await auth.register({ ...form })
    toast.success('Account created. You can now sign in.')
    router.push({ name: 'login' })
  } catch (error) {
    errors.value = validationErrors(error)
    if (!Object.keys(errors.value).length) {
      toast.error(errorMessage(error, 'Unable to create the account.'))
    }
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <form class="flex flex-col gap-4 rounded-card border border-line bg-white p-card shadow-card"
    @submit.prevent="submit">
    <div>
      <h2 class="text-section-title">Create account</h2>
      <p class="mt-0.5 text-small text-ink-muted">Register to request a university vehicle.</p>
    </div>

    <BaseInput v-model="form.first_name" label="First name" placeholder="Juan" :error="errors.first_name" required />

    <BaseInput v-model="form.last_name" label="Last name" placeholder="Dela Cruz" :error="errors.last_name" required />

    <BaseInput v-model="form.email" label="Email" type="email" :error="errors.email" required />

    <BaseSelect v-model="form.role" label="Role" :options="roleOptions" :error="errors.role" required />

    <BaseInput v-model="form.password" label="Password" type="password" hint="At least 8 characters."
      :error="errors.password" required />

    <BaseInput v-model="form.password_confirmation" label="Confirm password" type="password" required />

    <BaseButton type="submit" :loading="loading" block>Create account</BaseButton>

    <p class="text-center text-small text-ink-muted">
      Already registered?
      <RouterLink :to="{ name: 'login' }" class="font-medium text-primary hover:underline">
        Sign in
      </RouterLink>
    </p>
  </form>
</template>