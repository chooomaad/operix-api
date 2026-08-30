// Client HTTP sans keep-alive (agent:false) : une connexion par requete.
// Necessaire car le serveur `php -S` mono-thread gere mal la reutilisation de
// connexion sous Windows. Represente aussi mieux des clients distincts.
import http from 'node:http'

export function req(base, method, path, { token, body } = {}) {
  const u = new URL(base + path)
  const payload = body ? JSON.stringify(body) : null
  const headers = { Accept: 'application/json', Connection: 'close' }
  if (token) headers.Authorization = `Bearer ${token}`
  if (payload) { headers['Content-Type'] = 'application/json'; headers['Content-Length'] = Buffer.byteLength(payload) }
  return new Promise((resolve) => {
    const t0 = performance.now()
    const r = http.request(
      { hostname: u.hostname, port: u.port, path: u.pathname + u.search, method, headers, agent: false },
      (res) => {
        let data = ''
        res.on('data', (c) => (data += c))
        res.on('end', () => resolve({
          status: res.statusCode,
          ms: performance.now() - t0,
          q: res.headers['x-query-count'],
          dbms: res.headers['x-db-ms'],
          body: data,
        }))
      },
    )
    r.on('error', (e) => resolve({ status: 0, ms: performance.now() - t0, error: e.code || e.message }))
    if (payload) r.write(payload)
    r.end()
  })
}

export async function login(base, matricule, pin) {
  const r = await req(base, 'POST', '/auth/login', { body: { matricule, pin, platform: 'web' } })
  if (r.status !== 200) throw new Error(`login ${matricule} -> ${r.status} ${r.body?.slice(0,120)}`)
  return JSON.parse(r.body).token
}
