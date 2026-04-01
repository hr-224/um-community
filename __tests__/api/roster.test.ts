import { GET } from '@/app/api/roster/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { communityMember: { findMany: jest.fn(), count: jest.fn() } },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const ctx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1' } as any, member: { role: 'MEMBER' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('GET returns members list', async () => {
  ;(prisma.communityMember.findMany as jest.Mock).mockResolvedValue([
    { id: 'm1', role: 'ADMIN', status: 'ACTIVE', user: { email: 'a@b.com', avatar: null }, department: null, rank: null },
  ])
  ;(prisma.communityMember.count as jest.Mock).mockResolvedValue(1)
  const res = await GET(new Request('http://localhost?page=1'), ctx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.members).toHaveLength(1)
  expect(json.total).toBe(1)
})

test('GET filters by departmentId', async () => {
  ;(prisma.communityMember.findMany as jest.Mock).mockResolvedValue([])
  ;(prisma.communityMember.count as jest.Mock).mockResolvedValue(0)
  await GET(new Request('http://localhost?departmentId=d1'), ctx)
  expect(prisma.communityMember.findMany).toHaveBeenCalledWith(
    expect.objectContaining({ where: expect.objectContaining({ departmentId: 'd1' }) })
  )
})
