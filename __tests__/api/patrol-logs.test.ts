import { GET, POST } from '@/app/api/patrol-logs/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { patrolLog: { findMany: jest.fn(), create: jest.fn() } },
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
  member: { role: 'MEMBER' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('GET returns patrol logs', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.patrolLog.findMany as jest.Mock).mockResolvedValue([{ id: 'p1', memberId: 'u1', startTime: new Date() }])
  const res = await GET(new Request('http://localhost'), ctx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.patrolLogs).toHaveLength(1)
})

test('GET returns 403 on FREE plan', async () => {
  ;(checkFeatureAccess as jest.Mock).mockImplementation(() => { throw new FeatureGatedError('patrolLogs', 'FREE') })
  const res = await GET(new Request('http://localhost'), ctx)
  expect(res.status).toBe(403)
})

test('POST creates patrol log', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  ;(prisma.patrolLog.create as jest.Mock).mockResolvedValue({ id: 'p1' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ startTime: new Date().toISOString() }),
  })
  const res = await POST(req, ctx)
  expect(res.status).toBe(201)
})

test('POST returns 400 for missing startTime', async () => {
  ;(checkFeatureAccess as jest.Mock).mockReturnValue(undefined)
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({}),
  })
  expect((await POST(req, ctx)).status).toBe(400)
})
