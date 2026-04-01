import { GET, PATCH } from '@/app/api/admin/settings/route'
import { POST as POST_INVITE } from '@/app/api/admin/invites/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    community: { findUnique: jest.fn(), update: jest.fn() },
    inviteLink: { create: jest.fn() },
  },
}))
jest.mock('nanoid', () => ({ nanoid: jest.fn(() => 'abc123xyz') }))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1', name: 'Test', isPublic: false } as any,
  member: { role: 'ADMIN' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('GET returns community settings', async () => {
  ;(prisma.community.findUnique as jest.Mock).mockResolvedValue({
    id: 'c1', name: 'Test', isPublic: false, autoApproveMembers: false, discordServerId: null,
  })
  const res = await GET(new Request('http://localhost'), adminCtx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.settings.name).toBe('Test')
})

test('PATCH updates community settings', async () => {
  ;(prisma.community.update as jest.Mock).mockResolvedValue({ id: 'c1', name: 'New Name', isPublic: true })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'New Name', isPublic: true }),
  })
  const res = await PATCH(req, adminCtx)
  expect(res.status).toBe(200)
})

test('PATCH returns 400 for invalid name', async () => {
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: '' }),
  })
  expect((await PATCH(req, adminCtx)).status).toBe(400)
})

test('POST invite creates invite link', async () => {
  ;(prisma.inviteLink.create as jest.Mock).mockResolvedValue({ id: 'inv1', code: 'abc123xyz' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ type: 'STANDARD' }),
  })
  const res = await POST_INVITE(req, adminCtx)
  expect(res.status).toBe(201)
  const json = await res.json()
  expect(json.inviteLink.code).toBe('abc123xyz')
})
