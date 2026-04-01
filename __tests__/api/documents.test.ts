import { GET, POST } from '@/app/api/documents/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { document: { findMany: jest.fn(), create: jest.fn() } },
}))
jest.mock('@/lib/plans', () => ({
  checkFeatureAccess: jest.fn(),
  FeatureGatedError: class FeatureGatedError extends Error {
    constructor(public feature: string, public tier: string) { super(`gated: ${feature}`); this.name = 'FeatureGatedError' }
  },
}))

import { prisma } from '@/lib/prisma'
import { checkFeatureAccess, FeatureGatedError } from '@/lib/plans'
import type { CommunityContext } from '@/lib/community-auth'

const ctx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1', planTier: 'STANDARD' } as any,
  member: { role: 'ADMIN' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('GET returns documents', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.document.findMany as jest.Mock).mockResolvedValue([{ id: 'd1', name: 'Policy.pdf', fileUrl: 'https://example.com/f.pdf' }])
  const res = await GET(new Request('http://localhost'), ctx)
  expect(res.status).toBe(200)
  expect((await res.json()).documents).toHaveLength(1)
})

test('GET returns 403 on FREE plan', async () => {
  ;(checkFeatureAccess as jest.Mock).mockImplementation(() => { throw new FeatureGatedError('documents', 'FREE') })
  expect((await GET(new Request('http://localhost'), ctx)).status).toBe(403)
})

test('POST creates document', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.document.create as jest.Mock).mockResolvedValue({ id: 'd1' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Policy.pdf', fileUrl: 'https://example.com/f.pdf' }),
  })
  expect((await POST(req, ctx)).status).toBe(201)
})

test('POST returns 400 for invalid URL', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Bad', fileUrl: 'not-a-url' }),
  })
  expect((await POST(req, ctx)).status).toBe(400)
})
