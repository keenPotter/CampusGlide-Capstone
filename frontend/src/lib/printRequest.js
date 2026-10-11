import api from '@/lib/api'

/**
 * Prints the Request for Use of Vehicle form (two copies on one Legal page).
 * The form is built by the backend, which only allows administrators
 * and only for approved requests.
 */
export async function printRequest(request) {
  if (!request || request.status !== 'approved') return

  const { data: html } = await api.get(`/vehicle-requests/${request.id}/print`, {
    responseType: 'text',
    headers: { Accept: 'text/html' },
  })

  const frame = document.createElement('iframe')
  frame.setAttribute('aria-hidden', 'true')
  frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;'
  document.body.appendChild(frame)

  const frameWindow = frame.contentWindow
  const frameDocument = frameWindow.document
  frameDocument.open()
  frameDocument.write(html)
  frameDocument.close()

  const cleanup = () => frame.remove()
  frameWindow.addEventListener('afterprint', cleanup)
  setTimeout(cleanup, 120000)

  // Wait for the logo to load before opening the print dialog.
  const pending = Array.from(frameDocument.images).filter((image) => !image.complete)
  let remaining = pending.length

  const print = () => {
    frameWindow.focus()
    frameWindow.print()
  }

  if (!remaining) {
    print()
    return
  }

  pending.forEach((image) => {
    const done = () => {
      remaining -= 1
      if (remaining === 0) print()
    }
    image.addEventListener('load', done)
    image.addEventListener('error', done)
  })
}