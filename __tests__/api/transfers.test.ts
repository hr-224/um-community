import { GET, POST } from '@/app/api/transfers/route'
import { PATCH } from '@/app/api/admin/transfers/[id]/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler, opts) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    transfer: { findMany: jest.fn(), create: jest.fn(), findFirst: jest.fn(), update: jest.fn() },
    communityMember: { findFirst: jest.fn(), update: jest.fn() },
    department: { findFirst: jest.fn() },
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

test('GET returns transfers', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.transfer.findMany as jest.Mock).mockResolvedValue([{ id: 't1', status: 'PENDING' }])
  expect((await GET(new Request('http://localhost'), ctx)).status).toBe(200)
})

test('GET returns 403 on FREE plan', async () => {
  ;(checkFeatureAccess as jest.Mock).mockImplementation(() => { throw new FeatureGatedError('transfers', 'FREE') })
  expect((await GET(new Request('http://localhost'), ctx)).status).toBe(403)
})

test('POST submits transfer request', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue({ id: 'mem1', departmentId: 'd1' })
  ;(prisma.department.findFirst as jest.Mock).mockResolvedValue({ id: 'd2', communityId: 'c1' })
  ;(prisma.transfer.create as jest.Mock).mockResolvedValue({ id: 't1' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ toDeptId: 'd2', reason: 'Career development' }),
  })
  expect((await POST(req, ctx)).status).toBe(201)
})

test('POST returns 400 if already in target dept', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue({ id: 'mem1', departmentId: 'd2' })
  ;(prisma.department.findFirst as jest.Mock).mockResolvedValue({ id: 'd2', communityId: 'c1' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ toDeptId: 'd2', reason: 'test' }),
  })
  expect((await POST(req, ctx)).status).toBe(400)
})

test('PATCH approves transfer', async () => {
  ;(prisma.transfer.findFirst as jest.Mock).mockResolvedValue({ id: 't1', communityId: 'c1', memberId: 'mem1', toDeptId: 'd2', status: 'PENDING' })
  ;(prisma.transfer.update as jest.Mock).mockResolvedValue({ id: 't1', status: 'APPROVED' })
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue({ id: 'mem1' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'approve' }),
  })
  const res = await PATCH(req, adminCtx, { params: Promise.resolve({ id: 't1' }) })
  expect(res.status).toBe(200)
})
