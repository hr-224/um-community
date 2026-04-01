import { GET, POST } from '@/app/api/shifts/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { shift: { findMany: jest.fn(), create: jest.fn() } },
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

test('GET returns shifts', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.shift.findMany as jest.Mock).mockResolvedValue([{ id: 's1', title: 'Night Shift' }])
  const res = await GET(new Request('http://localhost'), ctx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.shifts).toHaveLength(1)
})

test('GET returns 403 on FREE plan', async () => {
  ;(checkFeatureAccess as jest.Mock).mockImplementation(() => { throw new FeatureGatedError('shifts', 'FREE') })
  expect((await GET(new Request('http://localhost'), ctx)).status).toBe(403)
})

test('POST creates shift for ADMIN', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.shift.create as jest.Mock).mockResolvedValue({ id: 's1' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Night Shift', startAt: new Date().toISOString(), endAt: new Date(Date.now() + 3600000).toISOString(), slots: 5 }),
  })
  expect((await POST(req, ctx)).status).toBe(201)
})

test('POST returns 403 for MEMBER', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  const memberCtx = { ...ctx, member: { role: 'MEMBER' } as any }
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Shift', startAt: new Date().toISOString(), endAt: new Date(Date.now() + 3600000).toISOString() }),
  })
  expect((await POST(req, memberCtx)).status).toBe(403)
})
