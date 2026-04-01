import { GET } from '@/app/api/dashboard/stats/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    communityMember: { count: jest.fn() },
    application: { count: jest.fn() },
    department: { findMany: jest.fn() },
  },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const ctx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1', name: 'Test', planTier: 'FREE' } as any,
  member: { role: 'OWNER' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('returns dashboard stats', async () => {
  ;(prisma.communityMember.count as jest.Mock).mockResolvedValue(10)
  ;(prisma.application.count as jest.Mock).mockResolvedValue(3)
  ;(prisma.department.findMany as jest.Mock).mockResolvedValue([
    { id: 'd1', name: 'Police', _count: { members: 5 } },
  ])

  const res = await (GET as unknown as (req: Request, ctx: CommunityContext) => Promise<Response>)(new Request('http://localhost'), ctx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.memberCount).toBe(10)
  expect(json.pendingApplications).toBe(3)
  expect(json.departments).toHaveLength(1)
})
