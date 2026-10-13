import { formatDate, formatKm } from '@/lib/format'

const CHECK_ITEMS = [
  ['belts_condition', 'Belts'],
  ['hoses_condition', 'Hoses'],
  ['engine_condition', 'Engine'],
  ['air_conditioning_condition', 'Air conditioning'],
  ['wipers_condition', 'Wipers'],
  ['headlights_condition', 'Headlights'],
  ['driving_lights_condition', 'Driving lights'],
  ['brake_lights_condition', 'Brake lights'],
  ['hazard_lights_condition', 'Hazard lights'],
  ['door_locks_condition', 'Door locks'],
  ['windows_windshield_condition', 'Windows / windshield'],
  ['radio_condition', 'Radio'],
  ['tires_condition', 'Tires'],
  ['liquid_levels_condition', 'Liquid levels'],
  ['other_parts_condition', 'Other parts'],
]

const SERVICE_DATES = [
  ['last_oil_change', 'Oil change'],
  ['last_air_filter_change', 'Air filter'],
  ['last_cabin_filter_change', 'Cabin filter'],
  ['last_oil_filter_change', 'Oil filter'],
  ['last_engine_tune_up', 'Engine tune-up'],
]

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[character])
}

function value(value) {
  return escapeHtml(value || ' ')
}

function dateValue(value) {
  return value ? formatDate(value) : ''
}

function ratingCell(record, field, rating) {
  return record[field] === rating ? '&#10003;' : '&nbsp;'
}

function checklistHtml(record = {}) {
  const vehicle = record.vehicle ?? {}
  const serviceRows = SERVICE_DATES.map(([field, label]) => `
    <div><span>${label}</span><strong>${value(dateValue(record[field]))}</strong></div>
  `).join('')

  const itemRows = CHECK_ITEMS.map(([field, label]) => `
    <tr>
      <th scope="row">${label}</th>
      <td class="rating">${ratingCell(record, field, 'excellent')}</td>
      <td class="rating">${ratingCell(record, field, 'good')}</td>
      <td class="rating">${ratingCell(record, field, 'poor')}</td>
    </tr>
  `).join('')

  return `<!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <title>Preventive Maintenance Checklist</title>
        <style>
          @page { size: letter portrait; margin: 12mm; }
          * { box-sizing: border-box; }
          body { color: #111; font: 10pt Arial, sans-serif; margin: 0; }
          h1, h2, p { margin: 0; }
          .heading { border-bottom: 2px solid #111; padding-bottom: 8px; text-align: center; }
          .heading .university { font-size: 11pt; font-weight: 700; }
          .heading .title { font-size: 13pt; font-weight: 700; margin-top: 5px; }
          .topline { display: flex; justify-content: space-between; margin: 10px 0 7px; }
          .vehicle, .service-dates { border-collapse: collapse; width: 100%; }
          .vehicle { margin-bottom: 8px; }
          .vehicle td { border: 1px solid #333; height: 25px; padding: 4px 6px; }
          .label { color: #333; font-size: 8pt; font-weight: 700; }
          .service-dates { margin-bottom: 9px; }
          .service-dates td { border: 1px solid #555; padding: 4px 6px; width: 20%; }
          .service-dates span, .service-dates strong { display: block; }
          .service-dates span { font-size: 8pt; }
          .service-dates strong { margin-top: 5px; min-height: 12px; }
          .section-title { font-size: 9pt; margin: 7px 0 4px; }
          .items { border-collapse: collapse; table-layout: fixed; width: 100%; }
          .items th, .items td { border: 1px solid #333; padding: 4px 5px; }
          .items thead th { background: #f1f1f1; text-align: center; }
          .items th:first-child { text-align: left; width: 55%; }
          .items .rating { text-align: center; width: 15%; }
          .other-parts { border: 1px solid #333; min-height: 34px; padding: 5px; }
          .notes { border: 1px solid #333; min-height: 47px; padding: 5px; white-space: pre-wrap; }
          .signatures { display: grid; gap: 28px; grid-template-columns: 1fr 1fr; margin-top: 28px; }
          .signature { border-top: 1px solid #333; padding-top: 4px; text-align: center; }
          .signature small { display: block; margin-top: 2px; }
          @media print { body { print-color-adjust: exact; } }
        </style>
      </head>
      <body>
        <header class="heading">
          <p>Republic of the Philippines</p>
          <p class="university">NUEVA VIZCAYA STATE UNIVERSITY</p>
          <p>Bayombong, Nueva Vizcaya</p>
          <h1 class="title">PREVENTIVE MAINTENANCE CHECKLIST OF UNIVERSITY VEHICLES</h1>
        </header>

        <div class="topline">
          <span><b>PMUV No.:</b> ${value(record.pmuv_no)}</span>
          <span><b>Date:</b> ${value(dateValue(record.inspection_date))}</span>
        </div>

        <table class="vehicle">
          <tbody>
            <tr>
              <td><span class="label">Vehicle / Make and model</span><br>${value(vehicle.vehicle_model)}</td>
              <td><span class="label">Plate number</span><br>${value(vehicle.plate_number)}</td>
            </tr>
            <tr>
              <td><span class="label">Inspector / Mechanic</span><br>${value(record.inspector_mechanic)}</td>
              <td><span class="label">Current mileage</span><br>${value(record.current_mileage == null ? '' : formatKm(record.current_mileage))}</td>
            </tr>
          </tbody>
        </table>

        <h2 class="section-title">Last service dates</h2>
        <table class="service-dates"><tbody><tr>${serviceRows.replaceAll('<div>', '<td>').replaceAll('</div>', '</td>')}</tr></tbody></table>

        <h2 class="section-title">Vehicle condition (check one for each item)</h2>
        <table class="items">
          <thead><tr><th>Item</th><th>Excellent</th><th>Good</th><th>Poor</th></tr></thead>
          <tbody>${itemRows}</tbody>
        </table>

        <h2 class="section-title">Other parts inspected</h2>
        <div class="other-parts">${value(record.other_parts)}</div>

        <h2 class="section-title">Remarks</h2>
        <div class="notes">${value(record.remarks)}</div>

        <h2 class="section-title">Supervisor's recommendation</h2>
        <div class="notes">${value(record.supervisor_recommendation)}</div>

        <div class="signatures">
          <div class="signature">${value(record.inspector_mechanic)}<small>Inspector / Mechanic</small></div>
          <div class="signature"><small>Supervisor</small></div>
        </div>
      </body>
    </html>`
}

export function printPreventiveMaintenanceChecklist(record = {}) {
  const frame = document.createElement('iframe')
  frame.setAttribute('aria-hidden', 'true')
  frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;'
  document.body.appendChild(frame)

  const frameWindow = frame.contentWindow
  const frameDocument = frameWindow.document
  frameDocument.open()
  frameDocument.write(checklistHtml(record))
  frameDocument.close()

  const cleanup = () => frame.remove()
  frameWindow.addEventListener('afterprint', cleanup, { once: true })
  setTimeout(cleanup, 120000)

  frameWindow.addEventListener('load', () => {
    frameWindow.focus()
    frameWindow.print()
  }, { once: true })
}
