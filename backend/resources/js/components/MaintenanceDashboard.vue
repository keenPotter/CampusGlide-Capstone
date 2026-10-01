<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
const logs = ref([]), vehicles = ref([]), loading = ref(true), saving = ref(false), error = ref('');
const filterType = ref(''), filterVehicle = ref(''), showForm = ref(false);
const form = ref({ vehicle_id:'', type:'oil_change', description:'', date_performed:new Date().toISOString().slice(0,10), cost:'', next_due_date:'' });
const types = [['oil_change','Oil Change'],['repair','Repair'],['refueling','Refueling'],['inspection','Inspection'],['tire_service','Tire Service'],['other','Other']];
const filteredLogs = computed(() => logs.value.filter(log => (!filterType.value || log.type === filterType.value) && (!filterVehicle.value || String(log.vehicle?.id) === String(filterVehicle.value))));
function typeLabel(type){ return types.find(([value])=>value===type)?.[1] ?? type; }
function formatDate(value){ if(!value) return '—'; return new Date(`${value}T00:00:00`).toLocaleDateString('en-PH',{year:'numeric',month:'short',day:'numeric'}); }
async function loadData(){ loading.value=true; error.value=''; try { const [a,b]=await Promise.all([axios.get('/api/maintenance-logs'),axios.get('/api/vehicles')]); logs.value=a.data.data; vehicles.value=b.data.data; if(!form.value.vehicle_id && vehicles.value.length) form.value.vehicle_id=vehicles.value[0].id; } catch(e){ error.value=e.response?.data?.message||e.message||'Unable to load data.'; } finally { loading.value=false; } }
async function submitForm(){ saving.value=true; error.value=''; try { await axios.post('/api/maintenance-logs',{...form.value,vehicle_id:Number(form.value.vehicle_id),cost:form.value.cost===''?null:Number(form.value.cost),next_due_date:form.value.next_due_date||null}); showForm.value=false; form.value={vehicle_id:vehicles.value[0]?.id??'',type:'oil_change',description:'',date_performed:new Date().toISOString().slice(0,10),cost:'',next_due_date:''}; await loadData(); } catch(e){ const v=e.response?.data?.errors; error.value=v?Object.values(v).flat().join(' '):(e.response?.data?.message||e.message); } finally { saving.value=false; } }
onMounted(loadData);
</script>
<template>
<main class="page-shell">
<header class="topbar"><div><p class="eyebrow">CampusGlide</p><h1>Maintenance Monitoring</h1><p class="subtitle">Track vehicle maintenance, due dates, and readiness.</p></div><button class="primary" @click="showForm=!showForm">{{showForm?'Close':'+ Add Maintenance'}}</button></header>
<section v-if="showForm" class="card form-card"><div class="section-heading"><h2>Record maintenance</h2><p>Add a maintenance entry.</p></div><form @submit.prevent="submitForm" class="form-grid">
<label>Vehicle<select v-model="form.vehicle_id" required><option value="" disabled>Select vehicle</option><option v-for="v in vehicles" :key="v.id" :value="v.id">{{v.plate_number}} — {{v.vehicle_model}}</option></select></label>
<label>Maintenance type<select v-model="form.type" required><option v-for="[value,label] in types" :key="value" :value="value">{{label}}</option></select></label>
<label>Date performed<input v-model="form.date_performed" type="date" required></label><label>Next due date<input v-model="form.next_due_date" type="date" :min="form.date_performed"></label>
<label>Cost<input v-model="form.cost" type="number" min="0" step="0.01" placeholder="0.00"></label>
<label class="wide">Description<textarea v-model="form.description" maxlength="500" rows="3" placeholder="Describe the maintenance work..."></textarea></label>
<div class="form-actions wide"><button class="primary" :disabled="saving">{{saving?'Saving…':'Save maintenance'}}</button></div></form></section>
<p v-if="error" class="alert">{{error}}</p>
<section class="stats"><div class="stat card"><span>Total records</span><strong>{{logs.length}}</strong></div><div class="stat card"><span>Vehicles</span><strong>{{vehicles.length}}</strong></div><div class="stat card"><span>With due dates</span><strong>{{logs.filter(l=>l.next_due_date).length}}</strong></div></section>
<section class="card"><div class="section-heading row"><div><h2>Maintenance logs</h2><p>Latest entries from <code>vehicle_maintenance</code>.</p></div><div class="filters"><select v-model="filterVehicle"><option value="">All vehicles</option><option v-for="v in vehicles" :key="v.id" :value="v.id">{{v.plate_number}}</option></select><select v-model="filterType"><option value="">All types</option><option v-for="[value,label] in types" :key="value" :value="value">{{label}}</option></select></div></div>
<div v-if="loading" class="empty">Loading maintenance records…</div><div v-else-if="!filteredLogs.length" class="empty">No maintenance records match your filters.</div><div v-else class="table-wrap"><table><thead><tr><th>Vehicle</th><th>Type</th><th>Date performed</th><th>Next due</th><th>ID</th></tr></thead><tbody><tr v-for="log in filteredLogs" :key="log.id"><td><strong>{{log.vehicle?.plate_number||'Unknown'}}</strong></td><td><span class="badge">{{typeLabel(log.type)}}</span></td><td>{{formatDate(log.date_performed)}}</td><td>{{formatDate(log.next_due_date)}}</td><td>#{{log.id}}</td></tr></tbody></table></div></section>
</main>
</template>
