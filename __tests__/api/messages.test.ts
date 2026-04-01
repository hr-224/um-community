import { GET, POST } from '@/app/api/messages/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    message: { findMany: jest.fn(), create: jest.fn() },
    communityMember: { findFirst: jest.fn() },
  },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const ctx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1' } as any, member: { role: 'MEMBER' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('GET returns inbox messages', async () => {
  ;(prisma.message.findMany as jest.Mock).mockResolvedValue([
    { id: 'msg1', content: 'Hello', senderId: 'u2', recipientId: 'u1' },
  ])
  const res = await GET(new Request('http://localhost'), ctx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.messages).toHaveLength(1)
})

test('POST sends a message', async () => {
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue({ id: 'cm1', userId: 'u2', communityId: 'c1', status: 'ACTIVE' })
  ;(prisma.message.create as jest.Mock).mockResolvedValue({ id: 'msg1' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ recipientId: 'u2', content: 'Hello!' }),
  })
  expect((await POST(req, ctx)).status).toBe(201)
})

test('POST returns 400 if recipientId missing', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ content: 'Hello!' }),
  })
  expect((await POST(req, ctx)).status).toBe(400)
})

test('POST returns 400 if sending to self', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ recipientId: 'u1', content: 'Hello!' }),
  })
  expect((await POST(req, ctx)).status).toBe(400)
})
