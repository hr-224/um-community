import { GET, POST } from '@/app/api/announcements/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { announcement: { findMany: jest.fn(), create: jest.fn() } },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1', planTier: 'FREE' } as any,
  member: { role: 'ADMIN' } as any,
}
const memberCtx: CommunityContext = { ...adminCtx, member: { role: 'MEMBER' } as any }

beforeEach(() => jest.clearAllMocks())

test('GET returns announcements pinned first', async () => {
  const rows = [
    { id: 'a1', title: 'Pinned', isPinned: true },
    { id: 'a2', title: 'Normal', isPinned: false },
  ]
  ;(prisma.announcement.findMany as jest.Mock).mockResolvedValue(rows)
  const res = await GET(new Request('http://localhost'), adminCtx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.announcements[0].isPinned).toBe(true)
})

test('POST returns 403 for MEMBER role', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Hello', content: 'World' }),
  })
  const res = await POST(req, memberCtx)
  expect(res.status).toBe(403)
})

test('POST creates announcement for ADMIN', async () => {
  ;(prisma.announcement.create as jest.Mock).mockResolvedValue({ id: 'a1', title: 'Hello' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Hello', content: 'World' }),
  })
  const res = await POST(req, adminCtx)
  expect(res.status).toBe(201)
})

test('POST returns 400 for missing title', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: '', content: 'World' }),
  })
  const res = await POST(req, adminCtx)
  expect(res.status).toBe(400)
})
