<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import api, { errorMessage, validationErrors } from '@/lib/api'
import { useToast } from '@/composables/useToast'
import BaseCard from '@/components/ui/BaseCard.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import RefreshButton from '@/components/ui/RefreshButton.vue'
import BaseInput from '@/components/ui/BaseInput.vue'
import BaseSelect from '@/components/ui/BaseSelect.vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import LoadError from '@/components/ui/LoadError.vue'
import { formatDateTime, fullName, ROLE_LABELS } from '@/lib/format'

const toast = useToast()
const users = ref([])
const paginationMeta = ref(null)
const loading = ref(true)
const loadError = ref('')
const search = ref('')
const page = ref(1)
const roleFilter = ref('')
const deletionFilter = ref('')
const editing = ref(null)
const editForm = ref(null)
const confirmDeleteUser = ref(null)
const saving = ref(false)
const showCreate = ref(false)
const createErrors = ref({})
const createForm = reactive({ first_name: '', last_name: '', email: '', position: '', phone_number: '', role: '', password: '', password_confirmation: '' })
const createRoleOptions = [
  { value: 'administrator', label: 'Administrator' },
  { value: 'faculty', label: 'Employee' },
  { value: 'staff', label: 'Staff' },
]
const roleOptions = [
  { value: 'faculty', label: 'Employee' },
  { value: 'staff', label: 'Staff' },
  { value: 'administrator', label: 'Administrator' },
]
const deletionOptions = [{ value: 'pending', label: 'Pending deletion' }]

async function loadUsers() {
  loading.value = true
  loadError.value = ''
  try {
    const response = await api.get('/users', {
      params: {
        page: page.value,
        search: search.value || undefined,
        role: roleFilter.value || undefined,
        deletion_status: deletionFilter.value || undefined,
      },
    })
    users.value = response.data?.data ?? []
    paginationMeta.value = response.data?.meta ?? null
  } catch (error) {
    loadError.value = errorMessage(error, 'Unable to load users.')
  } finally { loading.value = false }
}

function changePage(value) {
  page.value = value
  loadUsers()
}

watch(search, (_, __, onCleanup) => {
  page.value = 1
  const timeout = setTimeout(loadUsers, 250)
  onCleanup(() => clearTimeout(timeout))
})

watch([roleFilter, deletionFilter], () => {
  page.value = 1
  loadUsers()
})

function openCreate() {
  Object.assign(createForm, { first_name: '', last_name: '', email: '', position: '', phone_number: '', role: '', password: '', password_confirmation: '' })
  createErrors.value = {}
  showCreate.value = true
}
async function createUser() {
  saving.value = true
  createErrors.value = {}
  try {
    await api.post('/register', { ...createForm })
    showCreate.value = false
    toast.success('User created successfully.')
    await loadUsers()
  } catch (error) {
    createErrors.value = validationErrors(error)
    if (!Object.keys(createErrors.value).length) toast.error(errorMessage(error, 'Unable to create the user.'))
  } finally { saving.value = false }
}
function startEdit(user) {
  editing.value = user.id
  editForm.value = { first_name: user.first_name, last_name: user.last_name, email: user.email, position: user.position ?? '', phone_number: user.phone_number ?? '', role: user.role, password: '' }
}
function cancelEdit() { editing.value = null; editForm.value = null }
async function saveEdit(user) {
  saving.value = true
  try {
    const payload = { ...editForm.value }
    if (!payload.password) delete payload.password
    const response = await api.put(`/users/${user.id}`, payload)
    const updated = response.data?.data ?? response.data
    users.value = users.value.map((item) => item.id === user.id ? updated : item)
    cancelEdit()
    toast.success('User account updated.')
  } catch (error) { toast.error(errorMessage(error, 'Unable to update user account.')) }
  finally { saving.value = false }
}
async function requestDelete() {
  if (!confirmDeleteUser.value) return
  saving.value = true
  try {
    await api.delete(`/users/${confirmDeleteUser.value.id}`)
    toast.success('Account disabled and scheduled for deletion in 30 days. It can be restored during that period.')
    confirmDeleteUser.value = null
    await loadUsers()
    if (!users.value.length && page.value > 1) {
      page.value -= 1
      await loadUsers()
    }
  } catch (error) { toast.error(errorMessage(error, 'Unable to schedule account deletion.')) }
  finally { saving.value = false }
}
async function cancelDeletion(user) {
  saving.value = true
  try {
    const response = await api.post(`/users/${user.id}/cancel-deletion`)
    const restored = response.data?.data ?? response.data
    users.value = users.value.map((item) => item.id === user.id ? restored : item)
    toast.success('Account deletion cancelled. The account is active again.')
  } catch (error) { toast.error(errorMessage(error, 'Unable to cancel deletion.')) }
  finally { saving.value = false }
}
onMounted(loadUsers)
</script>

<template>
  <div class="flex flex-col gap-page">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h1 class="text-page-title">User accounts</h1>
        <p class="mt-1 text-small text-ink-muted">View and manage CampusGlide accounts. Administrator accounts cannot be scheduled for deletion.</p>
      </div>
      <div class="flex items-center gap-2">
        <RefreshButton :loading="loading" label="Refresh users" @click="loadUsers" />
        <BaseButton size="sm" @click="openCreate">+ Create user</BaseButton>
      </div>
    </div>

    <BaseCard>
      <div class="mb-4 flex flex-col gap-3">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div class="font-semibold">Accounts <span class="text-small font-normal text-ink-muted">({{ paginationMeta?.total ?? 0 }})</span></div>
          <div class="flex min-w-0 flex-1">
            <BaseInput
              v-model="search"
              class="min-w-0 flex-1 sm:w-72 sm:flex-none"
              type="search"
              placeholder="Search users..."
            />
          </div>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <BaseSelect v-model="roleFilter" :options="roleOptions" placeholder="All roles" allow-empty />
          <BaseSelect v-model="deletionFilter" :options="deletionOptions" placeholder="All account statuses" allow-empty />
        </div>
      </div>
      <div v-if="loading" class="flex justify-center p-12" aria-label="Loading accounts">
        <div class="h-8 w-8 animate-spin rounded-full border-2 border-line border-t-primary" />
      </div>
      <LoadError v-else-if="loadError" :message="loadError" @retry="loadUsers" />
      <div v-else-if="!users.length" class="py-8 text-center text-ink-muted">No user accounts found.</div>
      <div v-else class="space-y-3">
        <article v-for="user in users" :key="user.id" class="rounded-card border border-line p-4 transition hover:border-primary-200 hover:shadow-sm">
          <template v-if="editing === user.id">
            <form class="grid gap-3 sm:grid-cols-2" @submit.prevent="saveEdit(user)">
              <BaseInput v-model="editForm.first_name" label="First name" required />
              <BaseInput v-model="editForm.last_name" label="Last name" required />
              <BaseInput v-model="editForm.email" label="Email" type="email" required />
              <BaseSelect v-model="editForm.role" label="Role" :options="roleOptions" :disabled="user.role === 'administrator'" required />
              <BaseInput v-model="editForm.position" label="Position / designation" />
              <BaseInput v-model="editForm.phone_number" label="Phone number" />
              <div class="sm:col-span-2"><BaseInput v-model="editForm.password" label="New password (optional)" type="password" hint="Leave blank to keep the current password." /></div>
              <div class="flex flex-wrap justify-end gap-2 sm:col-span-2">
                <BaseButton type="button" size="sm" variant="outline" @click="cancelEdit">Cancel</BaseButton>
                <BaseButton type="submit" size="sm" :loading="saving">Confirm changes</BaseButton>
              </div>
            </form>
          </template>
          <template v-else>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <h2 class="text-base font-semibold">{{ fullName(user) }}</h2>
                  <span class="rounded-full bg-primary-50 px-2.5 py-1 text-xs font-semibold text-primary-700">{{ ROLE_LABELS[user.role] ?? user.role }}</span>
                  <span v-if="user.deletion_requested_at" class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">Pending deletion</span>
                  <span v-else-if="!user.is_active" class="rounded-full bg-neutral-100 px-2.5 py-1 text-xs font-semibold text-neutral-600">Inactive</span>
                </div>
                <p class="mt-1 break-all text-small text-ink-muted">{{ user.email }}</p>
                <p v-if="user.position || user.phone_number" class="mt-1 text-small text-ink-muted">{{ [user.position, user.phone_number].filter(Boolean).join(' · ') }}</p>
                <p v-if="user.deletion_requested_at" class="mt-2 text-small text-red-700">Requested {{ formatDateTime(user.deletion_requested_at) }}. Deletion processing after {{ formatDateTime(user.deletion_due_at) }}. Historical requests, allocations, and trips will be retained.</p>
              </div>
              <div class="flex shrink-0 flex-wrap items-center gap-2">
                <BaseButton v-if="user.deletion_requested_at" variant="outline" size="sm" :loading="saving" @click="cancelDeletion(user)">Cancel deletion</BaseButton>
                <template v-else-if="user.role !== 'administrator'">
                  <BaseButton variant="outline" size="sm" @click="startEdit(user)">Edit</BaseButton>
                  <BaseButton variant="danger" size="sm" @click="confirmDeleteUser = user">Delete account</BaseButton>
                </template>
                <BaseButton v-else variant="outline" size="sm" @click="startEdit(user)">Edit</BaseButton>
              </div>
            </div>
          </template>
        </article>
      </div>
      <PaginationBar v-if="!loading" :meta="paginationMeta" @change="changePage" />
    </BaseCard>

    <BaseModal :open="showCreate" title="Create user account" subtitle="Add an administrator, employee, or staff account" @close="showCreate = false">
      <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="createUser">
        <BaseInput v-model="createForm.first_name" label="First name" :error="createErrors.first_name" required />
        <BaseInput v-model="createForm.last_name" label="Last name" :error="createErrors.last_name" required />
        <div class="sm:col-span-2"><BaseInput v-model="createForm.email" label="Email" type="email" :error="createErrors.email" required /></div>
        <BaseInput v-model="createForm.position" label="Position / designation" :error="createErrors.position" />
        <BaseInput v-model="createForm.phone_number" label="Phone number" type="tel" :error="createErrors.phone_number" />
        <BaseSelect v-model="createForm.role" label="Role" :options="createRoleOptions" :error="createErrors.role" required />
        <div class="grid gap-4 sm:col-span-2 sm:grid-cols-2">
          <BaseInput v-model="createForm.password" label="Password" type="password" :error="createErrors.password" required />
          <BaseInput v-model="createForm.password_confirmation" label="Confirm password" type="password" :error="createErrors.password_confirmation" required />
        </div>
        <div class="flex justify-end gap-2 sm:col-span-2">
          <BaseButton type="button" variant="outline" @click="showCreate = false">Cancel</BaseButton>
          <BaseButton type="submit" :loading="saving">Create user</BaseButton>
        </div>
      </form>
    </BaseModal>

    <BaseModal :open="!!confirmDeleteUser" title="Delete User Account?" @close="confirmDeleteUser = null">
      <template v-if="confirmDeleteUser">
        <div class="space-y-4 text-body">
          <p>You are about to schedule this account for deletion.</p>
          <div class="rounded-card border border-line bg-neutral-50 p-3">
            <p><strong>User:</strong> {{ fullName(confirmDeleteUser) }}</p>
            <p class="mt-1 break-all"><strong>Email:</strong> {{ confirmDeleteUser.email }}</p>
          </div>
          <div>
            <p class="font-semibold">What will happen?</p>
            <ul class="mt-2 list-disc space-y-2 pl-5 text-small text-ink-muted">
              <li>The account will be disabled immediately, and the user will no longer be able to log in.</li>
              <li>The account is scheduled for permanent deletion after 30 full days. You can cancel the deletion before the deadline.</li>
              <li>Historical Vehicle Requests, Trip Schedules, Allocation records, and Maintenance records will be preserved according to the school's retention policy. Personal account details will be anonymized as part of deletion processing.</li>
            </ul>
          </div>
          <p>Please confirm that you want to proceed. This action will immediately disable the user's account.</p>
        </div>
        <div class="mt-6 flex justify-end gap-2">
          <BaseButton variant="outline" :disabled="saving" @click="confirmDeleteUser = null">Cancel</BaseButton>
          <BaseButton variant="danger" :loading="saving" @click="requestDelete">Confirm deletion</BaseButton>
        </div>
      </template>
    </BaseModal>
  </div>
</template>
