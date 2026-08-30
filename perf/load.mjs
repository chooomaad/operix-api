import { req, login } from './http.mjs'

const POOL = (process.env.POOL ?? '8010,8011,8012,8013,8014,8015,8016,8017')
  .split(',').map((p) => `http://127.0.0.1:${p.trim()}/api/v1`)
let _rr = 0
const nextBase = () => POOL[_rr++ % POOL.length]
const VUS = Number(process.env.VUS ?? 20)
const DURATION_MS = Number(process.env.DURATION ?? 25000)
const THINK_MS = Number(process.env.THINK ?? 200) // pause realiste entre etapes

const ACCOUNTS = [
  { matricule: 'LOAD-ADMIN', pin: '739124', role: 'company_admin' },
  { matricule: 'LOAD-HM',    pin: '739124', role: 'hsse_manager' },
  { matricule: 'LOAD-AGENT', pin: '739124', role: 'agent' },
]

const sleep = (ms) => new Promise((r) => setTimeout(r, ms))
const samples = [] // {ep, status, ms, cls}

function classify(status) {
  if (status === 0) return 'conn_error'
  if (status >= 500) return '5xx'
  if ([401, 403, 404, 422].includes(status)) return '4xx_expected'
  if (status >= 400) return '4xx_unexpected'
  return 'ok'
}

async function step(token, ep, method, path, body) {
  const r = await req(nextBase(), method, path, { token, body })
  samples.push({ ep, status: r.status, ms: r.ms, cls: classify(r.status) })
  return r
}

async function journey(token) {
  await step(token, 'dashboard', 'GET', '/dashboard'); await sleep(THINK_MS)
  await step(token, 'incidents_list', 'GET', '/incidents?per_page=20'); await sleep(THINK_MS)
  await step(token, 'employees_list', 'GET', '/employees?per_page=20'); await sleep(THINK_MS)
  await step(token, 'search', 'GET', '/search?q=a'); await sleep(THINK_MS)
  const inc = await step(token, 'incidents_list2', 'GET', '/incidents?per_page=1')
  let id = null; try { id = JSON.parse(inc.body).data?.[0]?.id } catch {}
  if (id) { await step(token, 'incident_view', 'GET', `/incidents/${id}`); await sleep(THINK_MS) }
  await step(token, 'notifications', 'GET', '/notifications?per_page=20'); await sleep(THINK_MS)
  await step(token, 'incident_create', 'POST', '/incidents', {
    date: '2026-08-30', location: 'Quai', type: 'FAC', severity: 'low',
    description: 'Load test journey incident.',
  }); await sleep(THINK_MS)
  await step(token, 'dashboard2', 'GET', '/dashboard'); await sleep(THINK_MS)
  await step(token, 'audit', 'GET', '/audit?per_page=20'); await sleep(THINK_MS)
}

const RAMP_MS = Number(process.env.RAMP ?? 3000)

async function vu(token, endAt) {
  while (performance.now() < endAt) {
    await journey(token)
  }
}

function pct(arr, p) {
  if (!arr.length) return 0
  const s = [...arr].sort((a, b) => a - b)
  return s[Math.min(s.length - 1, Math.floor((p / 100) * s.length))]
}

// ── Phase de setup : authentification SEQUENTIELLE (hors mesure) ──
// Evite un thundering-herd de bcrypt au demarrage, artefact du test, pas de l'app.
const tokens = []
const loginMs = []
for (const acc of ACCOUNTS) {
  const t = performance.now()
  const tok = await login(nextBase(), acc.matricule, acc.pin)
  loginMs.push(performance.now() - t)
  tokens.push(tok)
}
console.log(`login (sequentiel) ms: ${loginMs.map((m)=>m.toFixed(0)).join(', ')}`)

// ── Phase mesuree : parcours de navigation concurrents, ramp-up ──
const t0 = performance.now()
const endAt = t0 + DURATION_MS
await Promise.all(Array.from({ length: VUS }, async (_, i) => {
  await sleep((i / VUS) * RAMP_MS)
  await vu(tokens[i % tokens.length], endAt)
}))
const elapsed = (performance.now() - t0) / 1000

const all = samples.map((s) => s.ms).filter((_, i) => samples[i].cls !== 'conn_error')
const by = (c) => samples.filter((s) => s.cls === c).length
console.log(`\n=== ${VUS} VUs / ${elapsed.toFixed(1)}s ===`)
console.log(`requests      : ${samples.length}`)
console.log(`throughput    : ${(samples.length / elapsed).toFixed(1)} req/s`)
console.log(`ok(2xx)       : ${by('ok')}`)
console.log(`4xx expected  : ${by('4xx_expected')}`)
console.log(`4xx unexpected: ${by('4xx_unexpected')}`)
console.log(`5xx           : ${by('5xx')}`)
console.log(`conn errors   : ${by('conn_error')}`)
console.log(`p50/p90/p95/p99 ms: ${pct(all,50).toFixed(0)} / ${pct(all,90).toFixed(0)} / ${pct(all,95).toFixed(0)} / ${pct(all,99).toFixed(0)}`)
// top endpoints par p95
const eps = [...new Set(samples.map((s) => s.ep))]
console.log('\n endpoint            n     p50   p95   p99  5xx')
for (const ep of eps) {
  const xs = samples.filter((s) => s.ep === ep)
  const ms = xs.filter((s)=>s.cls!=='conn_error').map((s) => s.ms)
  console.log(` ${ep.padEnd(18)} ${String(xs.length).padStart(4)} ${pct(ms,50).toFixed(0).padStart(5)} ${pct(ms,95).toFixed(0).padStart(5)} ${pct(ms,99).toFixed(0).padStart(5)} ${String(xs.filter(s=>s.cls==='5xx').length).padStart(4)}`)
}
