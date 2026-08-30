import { req, login } from './http.mjs'
const BASE = process.env.BASE ?? 'http://127.0.0.1:8002/api/v1'

const token = await login(BASE, 'LOAD-ADMIN', '739124')
const l = await req(BASE, 'GET', '/incidents?per_page=1', { token })
const firstId = JSON.parse(l.body).data?.[0]?.id

const endpoints = [
  ['GET', '/dashboard'], ['GET', '/dashboard/safety-timeline?year=2026'],
  ['GET', '/dashboard/employee-breakdown'], ['GET', '/dashboard/top-zones'],
  ['GET', '/incidents?per_page=20'], ['GET', '/near-miss?per_page=20'],
  ['GET', '/environment?per_page=20'], ['GET', '/employees?per_page=20'],
  ['GET', '/search?q=a'], ['GET', `/incidents/${firstId}`],
  ['GET', '/notifications?per_page=20'], ['GET', '/visitors?per_page=20'],
  ['GET', '/contractors?per_page=20'], ['GET', '/permits?per_page=20'],
  ['GET', '/equipment?per_page=20'], ['GET', '/audit?per_page=20'],
]
// warm
for (const [m,p] of endpoints) await req(BASE, m, p, { token })
console.log(' endpoint                                    status   ms    SQL   DBms')
for (const [m,p] of endpoints) {
  const r = await req(BASE, m, p, { token })
  console.log(` ${(m+' '+p).padEnd(44)} ${String(r.status).padEnd(6)} ${r.ms.toFixed(0).padStart(4)} ${String(r.q??'-').padStart(5)} ${String(r.dbms??'-').padStart(6)}`)
}
const c = await req(BASE, 'POST', '/incidents', { token, body: { date:'2026-08-30', location:'Quai 5', type:'Fire', severity:'high', description:'Load baseline create.' } })
console.log(`\n POST /incidents (create): ${c.status} ${c.ms.toFixed(0)}ms SQL=${c.q} DBms=${c.dbms}`)
