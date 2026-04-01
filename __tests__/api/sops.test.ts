import { GET, POST } from '@/app/api/sops/route'
import { GET as GET_ONE } from '@/app/api/sops/[id]/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { sOP: { findMany: jest.fn(), create: jest.fn(), findFirst: jest.fn() } },
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

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1', planTier: 'STANDARD' } as any,
  member: { role: 'ADMIN' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('GET returns SOPs list', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.sOP.findMany as jest.Mock).mockResolvedValue([{ id: 's1', title: 'Use of Force', version: '1.0' }])
  const res = await GET(new Request('http://localhost'), adminCtx)
  expect(res.status).toBe(200)
  expect((await res.json()).sops).toHaveLength(1)
})

test('GET returns 403 on FREE plan', async () => {
  ;(checkFeatureAccess as jest.Mock).mockImplementation(() => { throw new FeatureGatedError('sops', 'FREE') })
  expect((await GET(new Request('http://localhost'), adminCtx)).status).toBe(403)
})

test('POST creates SOP for ADMIN', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.sOP.create as jest.Mock).mockResolvedValue({ id: 's1', title: 'Test SOP' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Test SOP', content: 'Content here' }),
  })
  expect((await POST(req, adminCtx)).status).toBe(201)
})

test('POST returns 403 for MEMBER', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  const memberCtx = { ...adminCtx, member: { role: 'MEMBER' } as any }
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'SOP', content: 'content' }),
  })
  expect((await POST(req, memberCtx)).status).toBe(403)
})

test('GET single returns 404 if not found', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.sOP.findFirst as jest.Mock).mockResolvedValue(null)
  const res = await GET_ONE(new Request('http://localhost'), adminCtx, { params: Promise.resolve({ id: 'missing' }) })
  expect(res.status).toBe(404)
})
