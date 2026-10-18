<script setup>
import { useAuthStore } from '@/stores/auth'
import AdminAllocations from '@/components/allocations/AdminAllocations.vue'
import FacultyAllocations from '@/components/allocations/FacultyAllocations.vue'
import StaffAllocationsView from '@/views/allocations/StaffAllocationsView.vue'

const auth = useAuthStore()
</script>

<template>
  <AdminAllocations v-if="auth.isAdministrator" />
  <StaffAllocationsView v-else-if="auth.isStaff" />
  <FacultyAllocations v-else />
</template>
