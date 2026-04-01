import { GET, POST } from '@/app/api/events/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { event: { findMany: jest.fn(), create: jest.fn() } },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1' } as any, member: { role: 'ADMIN' } as any,
}
const memberCtx: CommunityContext = { ...adminCtx, member: { role: 'MEMBER' } as any }

beforeEach(() => jest.clearAllMocks())

test('GET returns events list', async () => {
  ;(prisma.event.findMany as jest.Mock).mockResolvedValue([{ id: 'e1', title: 'Patrol Night' }])
  const res = await GET(new Request('http://localhost'), adminCtx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.events).toHaveLength(1)
})

test('POST returns 403 for MEMBER role', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Event', startAt: new Date().toISOString() }),
  })
  expect((await POST(req, memberCtx)).status).toBe(403)
})

test('POST creates event for ADMIN', async () => {
  ;(prisma.event.create as jest.Mock).mockResolvedValue({ id: 'e1', title: 'Event' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Event', startAt: new Date().toISOString() }),
  })
  expect((await POST(req, adminCtx)).status).toBe(201)
})

test('POST returns 400 for missing title', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: '', startAt: new Date().toISOString() }),
  })
  expect((await POST(req, adminCtx)).status).toBe(400)
})
