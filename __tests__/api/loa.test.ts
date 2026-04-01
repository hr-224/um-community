import { GET, POST } from '@/app/api/loa/route'
import { PATCH } from '@/app/api/admin/loa/[id]/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler, opts) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    lOA: { findMany: jest.fn(), create: jest.fn(), findFirst: jest.fn(), update: jest.fn() },
    communityMember: { findFirst: jest.fn() },
  },
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
  member: { role: 'MEMBER', id: 'mem1' } as any,
}
const adminCtx: CommunityContext = { ...ctx, member: { role: 'ADMIN', id: 'mem1' } as any }

beforeEach(() => jest.clearAllMocks())

test('GET returns LOAs', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.lOA.findMany as jest.Mock).mockResolvedValue([{ id: 'l1', memberId: 'u1', status: 'PENDING' }])
  const res = await GET(new Request('http://localhost'), ctx)
  expect(res.status).toBe(200)
  expect((await res.json()).loas).toHaveLength(1)
})

test('GET returns 403 on FREE plan', async () => {
  ;(checkFeatureAccess as jest.Mock).mockImplementation(() => { throw new FeatureGatedError('loa', 'FREE') })
  expect((await GET(new Request('http://localhost'), ctx)).status).toBe(403)
})

test('POST submits LOA request', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue({ id: 'mem1' })
  ;(prisma.lOA.create as jest.Mock).mockResolvedValue({ id: 'l1' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ startDate: '2026-05-01', endDate: '2026-05-07', reason: 'Vacation' }),
  })
  expect((await POST(req, ctx)).status).toBe(201)
})

test('PATCH approves LOA', async () => {
  ;(prisma.lOA.findFirst as jest.Mock).mockResolvedValue({ id: 'l1', communityId: 'c1', status: 'PENDING' })
  ;(prisma.lOA.update as jest.Mock).mockResolvedValue({ id: 'l1', status: 'APPROVED' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'approve' }),
  })
  const res = await PATCH(req, adminCtx, { params: Promise.resolve({ id: 'l1' }) })
  expect(res.status).toBe(200)
  expect((await res.json()).loa.status).toBe('APPROVED')
})

test('PATCH returns 404 if LOA not found', async () => {
  ;(prisma.lOA.findFirst as jest.Mock).mockResolvedValue(null)
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'approve' }),
  })
  expect((await PATCH(req, adminCtx, { params: Promise.resolve({ id: 'missing' }) })).status).toBe(404)
})
