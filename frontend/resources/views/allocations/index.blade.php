<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vehicle-Driver Allocation — CampusGlide</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body{font-family:'Inter',sans-serif}
</style>
</head>
<body class="bg-gray-100">

<div class="max-w-md mx-auto bg-gray-50 min-h-screen pb-28 relative shadow-sm">

    <x-app-header title="Vehicle-Driver Allocation" />
    <x-search-bar id="searchInput" placeholder="Search by destination, driver, plate..." />
    <x-auth-panel />

    <div id="noAccessBanner" class="hidden mx-4 mt-3 text-xs text-yellow-700 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2">
        View-only: assigning vehicles and drivers is restricted to the Motor Pool Administrator.
    </div>

    <div class="grid grid-cols-4 gap-2 px-4 pt-3">
        <x-stat-card value-id="statScheduled" label="Scheduled" color="blue" />
        <x-stat-card value-id="statProgress"  label="Ongoing"   color="yellow" />
        <x-stat-card value-id="statCompleted" label="Done"      color="green" />
        <x-stat-card value-id="statCancelled" label="Cancelled" color="red" />
    </div>

    <div id="addAllocationBtn" class="hidden px-4 pt-3 flex justify-end">
        <x-primary-button onclick="newAllocation()">+ New Allocation</x-primary-button>
    </div>

    <div class="px-4 pt-3 space-y-2" id="listContainer"></div>

    <x-account-chip />
    <x-allocation-modal />

</div>

<script>
const API_BASE = '/api';
const ACTIVE = ['scheduled', 'in_progress'];
let allAllocations = [];

function esc(s){
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

function getToken(){ return localStorage.getItem('trip_token'); }
function authHeaders(extra={}){ return { 'Authorization':'Bearer '+getToken(), 'Accept':'application/json', ...extra }; }
function toggleAuthPanel(){ document.getElementById('authPanel').classList.toggle('hidden'); }

function getRole(){ return localStorage.getItem('trip_role'); }
function isAdmin(){ return getRole() === 'administrator'; }
function applyRoleUI(){
  const admin = isAdmin();
  const loggedIn = !!getToken();
  document.getElementById('loginForm').classList.toggle('hidden', loggedIn);
  document.getElementById('accountBox').classList.toggle('hidden', !loggedIn);
  document.getElementById('accountInfo').textContent = 'Logged in as ' + (getRole() || 'user');
  document.getElementById('addAllocationBtn').classList.toggle('hidden', !admin);
  document.getElementById('noAccessBanner').classList.toggle('hidden', !(loggedIn && !admin));
}

function updateAccountChip(loggedIn, label){
  document.getElementById('accountDot').className = 'w-2 h-2 rounded-full ' + (loggedIn ? 'bg-green-500' : 'bg-gray-300');
  document.getElementById('accountLabel').textContent = loggedIn ? (label || 'Logged in') : 'Not logged in';
}

function logoutLocal(msg){
  localStorage.removeItem('trip_token');
  localStorage.removeItem('trip_role');
  allAllocations = [];
  renderList(); renderStats();
  updateAccountChip(false);
  applyRoleUI();
  document.getElementById('authPanel').classList.remove('hidden');
  document.getElementById('authStatus').textContent = msg || '';
}

function firstError(data, res){
  const list = Object.values(data?.errors || {}).flat();
  return list.length ? list.join(' ') : (data?.message || ('Request failed (' + res.status + ')'));
}

async function login(){
  const email = document.getElementById('loginEmail').value;
  const password = document.getElementById('loginPassword').value;
  const status = document.getElementById('authStatus');
  status.textContent = 'Logging in...';
  try{
    const res = await fetch(`${API_BASE}/login`, {
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json'},
      body: JSON.stringify({ email, password }),
    });
    const data = await res.json().catch(()=>({}));
    const token = data.token || data.access_token || data.data?.token;
    const role  = data.role || data.user?.role || data.data?.role || data.data?.user?.role || '';
    if(!res.ok || !token){ status.textContent = 'Login failed: ' + (data.message || res.status); return; }
    localStorage.setItem('trip_token', token);
    localStorage.setItem('trip_role', role);
    status.textContent = isAdmin() ? '✅ Logged in.' : '✅ Logged in (view-only — admin access required to assign vehicles/drivers).';
    updateAccountChip(true, email);
    applyRoleUI();
    loadAllocations();
  }catch(e){ status.textContent = 'Error: ' + e.message; }
}

function statusColor(status){
  const map = {
    scheduled:{bg:'bg-blue-100',text:'text-blue-700'},
    in_progress:{bg:'bg-yellow-100',text:'text-yellow-700'},
    completed:{bg:'bg-green-100',text:'text-green-700'},
    cancelled:{bg:'bg-red-100',text:'text-red-700'},
  };
  return map[status] || {bg:'bg-gray-100',text:'text-gray-600'};
}

function openModal(){ document.getElementById('modalOverlay').classList.remove('hidden'); }
function closeModal(){ document.getElementById('modalOverlay').classList.add('hidden'); document.getElementById('modalError').textContent = ''; }

function resetForm(){
  document.getElementById('modalTitle').textContent = 'New Allocation';
  document.getElementById('f_id').value = '';
  document.getElementById('f_notes').value = '';
  document.getElementById('f_status').value = '';
  document.getElementById('statusField').classList.add('hidden');
  document.getElementById('f_vehicle_request_id').disabled = false;
  document.getElementById('modalError').textContent = '';
}

async function fetchOptions(requestId){
  const q = requestId ? `?vehicle_request_id=${encodeURIComponent(requestId)}` : '';
  const res = await fetch(`${API_BASE}/allocation-options${q}`, { headers: authHeaders() });
  const data = await res.json().catch(()=>({}));
  if(res.status === 401){ closeModal(); logoutLocal('Session expired — please log in again.'); throw new Error('Unauthorized'); }
  if(!res.ok) throw new Error(data.message || ('Request failed (' + res.status + ')'));
  return data;
}

function setOptions(selectId, items, placeholder, selectedId){
  const el = document.getElementById(selectId);
  el.innerHTML = `<option value="">${esc(placeholder)}</option>` +
    items.map(i => `<option value="${esc(i.id)}">${esc(i.label)}</option>`).join('');
  el.value = selectedId ? String(selectedId) : '';
}

async function refreshResources(requestId, keep){
  const errEl = document.getElementById('modalError');
  try{
    const d = await fetchOptions(requestId);
    const vehicles = d.vehicles.slice();
    const drivers = d.drivers.slice();
    if(keep?.vehicle && !vehicles.some(v => v.id === keep.vehicle.id)) vehicles.unshift(keep.vehicle);
    if(keep?.driver && !drivers.some(x => x.id === keep.driver.id)) drivers.unshift(keep.driver);
    setOptions('f_vehicle_id', vehicles, vehicles.length ? '— select vehicle —' : 'No vehicle free', keep?.vehicle?.id);
    setOptions('f_driver_id', drivers, drivers.length ? '— select driver —' : 'No driver free', keep?.driver?.id);
    return d;
  }catch(e){ if(e.message !== 'Unauthorized') errEl.textContent = e.message; }
}

function onRequestChange(requestId){
  document.getElementById('modalError').textContent = '';
  refreshResources(requestId || '');
}

async function newAllocation(){
  if(!isAdmin()){ alert('Only the Motor Pool Administrator can manage allocations.'); return; }
  resetForm(); openModal();
  const d = await refreshResources('');
  if(d){
    setOptions('f_vehicle_request_id', d.requests,
      d.requests.length ? '— select approved request —' : 'No approved requests waiting', '');
  } else {
    setOptions('f_vehicle_request_id', [], 'Unable to load requests', '');
  }
}

async function editAllocation(id){
  if(!isAdmin()){ alert('Only the Motor Pool Administrator can reassign allocations.'); return; }
  const a = allAllocations.find(x => x.id === id);   // FIXED: hindi na JSON sa onclick attribute
  if(!a) return;
  resetForm();
  document.getElementById('modalTitle').textContent = 'Reassign Allocation #' + a.id;
  document.getElementById('f_id').value = a.id;
  document.getElementById('statusField').classList.remove('hidden');
  document.getElementById('f_status').value = a.status ?? '';
  document.getElementById('f_notes').value = a.notes ?? '';
  setOptions('f_vehicle_request_id',
    [{ id: a.vehicle_request?.id, label: `#${a.vehicle_request?.id} · ${a.trip?.destination ?? ''}` }], '', a.vehicle_request?.id);
  document.getElementById('f_vehicle_request_id').disabled = true;
  openModal();
  await refreshResources(a.vehicle_request?.id, {
    vehicle: { id: a.vehicle?.id, label: `${a.vehicle?.plate_number ?? ''} — ${a.vehicle?.vehicle_model ?? ''}` },
    driver:  { id: a.driver?.id,  label: `${a.driver?.name ?? ''} (current)` },
  });
}

async function saveAllocation(){
  if(!isAdmin()){ alert('Only the Motor Pool Administrator can manage allocations.'); return; }
  const id = document.getElementById('f_id').value;
  const errEl = document.getElementById('modalError');
  errEl.textContent = '';

  const vehicleId = Number(document.getElementById('f_vehicle_id').value) || undefined;
  const driverId = Number(document.getElementById('f_driver_id').value) || undefined;
  const notes = document.getElementById('f_notes').value || undefined;

  try{
    let res;
    if(id){
      const payload = { vehicle_id: vehicleId, driver_id: driverId, status: document.getElementById('f_status').value || undefined, notes };
      res = await fetch(`${API_BASE}/allocations/${id}`, { method:'PUT', headers: authHeaders({'Content-Type':'application/json'}), body: JSON.stringify(payload) });
    } else {
      const requestId = Number(document.getElementById('f_vehicle_request_id').value);
      if(!requestId || !vehicleId || !driverId){ errEl.textContent = 'Please select a request, a vehicle, and a driver.'; return; }
      const payload = { vehicle_request_id: requestId, vehicle_id: vehicleId, driver_id: driverId, notes };
      res = await fetch(`${API_BASE}/allocations`, { method:'POST', headers: authHeaders({'Content-Type':'application/json'}), body: JSON.stringify(payload) });
    }
    const data = await res.json().catch(()=>({}));
    if(res.status === 401){ closeModal(); logoutLocal('Session expired — please log in again.'); return; }
    if(!res.ok){ errEl.textContent = firstError(data, res); return; }
    closeModal(); loadAllocations();
  }catch(e){ errEl.textContent = 'Request failed: ' + e.message; }
}

async function cancelAllocation(id){
  if(!isAdmin()){ alert('Only the Motor Pool Administrator can cancel allocations.'); return; }
  if(!confirm('Cancel this allocation? This frees the vehicle and driver and marks the trip cancelled.')) return;
  try{
    const res = await fetch(`${API_BASE}/allocations/${id}`, { method:'DELETE', headers: authHeaders() });
    const data = await res.json().catch(()=>({}));
    if(res.status === 401){ logoutLocal('Session expired — please log in again.'); return; }
    if(!res.ok){ alert('Error: ' + firstError(data, res)); return; }
    loadAllocations();
  }catch(e){ alert('Request failed: ' + e.message); }
}

async function loadAllocations(){
  if(!getToken()) return;
  const box = document.getElementById('listContainer');
  try{
    const res = await fetch(`${API_BASE}/allocations?per_page=100`, { headers: authHeaders() });
    const data = await res.json().catch(()=>({}));
    if(res.status === 401){ logoutLocal('Session expired — please log in again.'); return; }
    if(!res.ok){
      box.innerHTML = `<p class="text-sm text-red-500 text-center py-6">${esc(data.message || ('Error ' + res.status))}</p>`;
      return;
    }
    allAllocations = data.data || data;
    renderList();
    renderStats();
  }catch(e){
    console.error(e);
    box.innerHTML = `<p class="text-sm text-red-500 text-center py-6">Cannot reach the server (${esc(e.message)}). Is php artisan serve running?</p>`;
  }
}

function renderCard(a){
  const c = statusColor(a.status);
  const admin = isAdmin();
  const active = ACTIVE.includes(a.status);
  return `<div class="bg-white rounded-2xl shadow-sm p-3 space-y-1">
    <div class="flex justify-between items-start">
      <div>
        <p class="font-semibold text-sm text-gray-800">${esc(a.trip?.destination ?? '—')}</p>
        <p class="text-xs text-gray-400">${esc(a.trip?.purpose ?? '')}</p>
        <p class="text-xs text-gray-400">Requested by: ${esc(a.vehicle_request?.requester ?? '—')}</p>
      </div>
      <span class="text-[10px] px-2 py-1 rounded-full font-medium ${c.bg} ${c.text}">${esc((a.status ?? 'unknown').replace('_',' '))}</span>
    </div>
    <p class="text-xs text-gray-500">📅 ${esc(a.trip?.trip_date ?? '—')} · 🕒 ${esc(a.trip?.departure_time ?? '—')} → ${esc(a.trip?.estimated_return_time ?? '—')}</p>
    <div class="flex flex-col gap-0.5 text-xs text-gray-600 bg-gray-50 rounded-lg p-2">
      <span>🚐 ${esc(a.vehicle?.vehicle_model ?? '—')} (${esc(a.vehicle?.plate_number ?? '—')})</span>
      <span>🧑‍✈️ ${esc(a.driver?.name ?? '—')} — Lic# ${esc(a.driver?.license_number ?? '—')}</span>
    </div>
    ${a.notes ? `<p class="text-xs text-gray-400 italic">Note: ${esc(a.notes)}</p>` : ''}
    ${(admin && active) ? `<div class="flex gap-2 pt-1">
      <button onclick="editAllocation(${Number(a.id)})" class="text-sm font-medium px-4 py-2 rounded-full shadow bg-yellow-500 text-white">Reassign</button>
      <button onclick="cancelAllocation(${Number(a.id)})" class="text-sm font-medium px-4 py-2 rounded-full shadow bg-red-600 text-white">Cancel</button>
    </div>` : ''}
  </div>`;
}

function renderList(){
  const q = (document.getElementById('searchInput').value || '').toLowerCase();
  const filtered = allAllocations.filter(a => {
    if(!q) return true;
    const haystack = [a.trip?.destination, a.driver?.name, a.vehicle?.plate_number, a.vehicle?.vehicle_model].join(' ').toLowerCase();
    return haystack.includes(q);
  });
  document.getElementById('listContainer').innerHTML = filtered.length
    ? filtered.map(renderCard).join('')
    : '<p class="text-sm text-gray-400 text-center py-6">No allocations found.</p>';
}

function renderStats(){
  const counts = { scheduled:0, in_progress:0, completed:0, cancelled:0 };
  allAllocations.forEach(a => { if(counts[a.status] !== undefined) counts[a.status]++; });
  document.getElementById('statScheduled').textContent = counts.scheduled;
  document.getElementById('statProgress').textContent = counts.in_progress;
  document.getElementById('statCompleted').textContent = counts.completed;
  document.getElementById('statCancelled').textContent = counts.cancelled;
}

document.getElementById('searchInput').addEventListener('input', renderList);

applyRoleUI();
if(getToken()){ updateAccountChip(true); loadAllocations(); }
</script>

</body>
</html>
