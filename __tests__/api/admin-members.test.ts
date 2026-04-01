import { PATCH, DELETE } from '@/app/api/admin/members/[id]/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler, opts) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    communityMember: {
      findFirst: jest.fn(),
      update: jest.fn(),
      delete: jest.fn(),
    },
  },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1' } as any, member: { role: 'ADMIN', id: 'own-member' } as any,
}

const fakeMember = { id: 'tm1', communityId: 'c1', userId: 'u2', role: 'MEMBER', status: 'ACTIVE' }

beforeEach(() => jest.clearAllMocks())

const params = { params: Promise.resolve({ id: 'tm1' }) }

test('PATCH updates member role', async () => {
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue(fakeMember)
  ;(prisma.communityMember.update as jest.Mock).mockResolvedValue({ ...fakeMember, role: 'MODERATOR' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ role: 'MODERATOR' }),
  })
  const res = await PATCH(req, adminCtx, params)
  expect(res.status).toBe(200)
})

test('PATCH returns 404 if member not found', async () => {
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue(null)
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ role: 'MODERATOR' }),
  })
  expect((await PATCH(req, adminCtx, params)).status).toBe(404)
})

test('PATCH returns 400 if trying to change OWNER role', async () => {
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue({ ...fakeMember, role: 'OWNER' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ role: 'MEMBER' }),
  })
  expect((await PATCH(req, adminCtx, params)).status).toBe(400)
})

test('DELETE removes member', async () => {
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue(fakeMember)
  ;(prisma.communityMember.delete as jest.Mock).mockResolvedValue(fakeMember)
  const res = await DELETE(new Request('http://localhost'), adminCtx, params)
  expect(res.status).toBe(200)
})

test('DELETE returns 400 when removing OWNER', async () => {
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue({ ...fakeMember, role: 'OWNER' })
  expect((await DELETE(new Request('http://localhost'), adminCtx, params)).status).toBe(400)
})
