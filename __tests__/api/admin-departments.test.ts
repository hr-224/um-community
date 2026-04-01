import { GET, POST } from '@/app/api/admin/departments/route'
import { PATCH, DELETE } from '@/app/api/admin/departments/[id]/route'
import { POST as POST_RANK } from '@/app/api/admin/departments/[id]/ranks/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    department: { findMany: jest.fn(), create: jest.fn(), findFirst: jest.fn(), update: jest.fn(), delete: jest.fn() },
    rank: { create: jest.fn() },
    communityMember: { count: jest.fn() },
  },
}))
jest.mock('@/lib/plans', () => ({
  checkPlanLimit: jest.fn(),
  PLANS: { FREE: { limits: { departments: 1 } }, STANDARD: { limits: { departments: 5 } }, PRO: { limits: { departments: null } } },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1', planTier: 'STANDARD' } as any,
  member: { role: 'ADMIN' } as any,
}

beforeEach(() => jest.clearAllMocks())

const deptParams = { params: Promise.resolve({ id: 'd1' }) }

test('GET returns departments with ranks', async () => {
  ;(prisma.department.findMany as jest.Mock).mockResolvedValue([{ id: 'd1', name: 'Police', ranks: [] }])
  const res = await GET(new Request('http://localhost'), adminCtx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.departments).toHaveLength(1)
})

test('POST creates department', async () => {
  ;(prisma.department.findMany as jest.Mock).mockResolvedValue([])
  ;(prisma.department.create as jest.Mock).mockResolvedValue({ id: 'd1', name: 'Fire' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Fire Department' }),
  })
  expect((await POST(req, adminCtx)).status).toBe(201)
})

test('PATCH updates department', async () => {
  ;(prisma.department.findFirst as jest.Mock).mockResolvedValue({ id: 'd1', communityId: 'c1' })
  ;(prisma.department.update as jest.Mock).mockResolvedValue({ id: 'd1', name: 'Updated' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Updated' }),
  })
  expect((await PATCH(req, adminCtx, deptParams)).status).toBe(200)
})

test('DELETE returns 400 if department has members', async () => {
  ;(prisma.department.findFirst as jest.Mock).mockResolvedValue({ id: 'd1', communityId: 'c1' })
  ;(prisma.communityMember.count as jest.Mock).mockResolvedValue(3)
  expect((await DELETE(new Request('http://localhost'), adminCtx, deptParams)).status).toBe(400)
})

test('DELETE removes department with no members', async () => {
  ;(prisma.department.findFirst as jest.Mock).mockResolvedValue({ id: 'd1', communityId: 'c1' })
  ;(prisma.communityMember.count as jest.Mock).mockResolvedValue(0)
  ;(prisma.department.delete as jest.Mock).mockResolvedValue({ id: 'd1' })
  expect((await DELETE(new Request('http://localhost'), adminCtx, deptParams)).status).toBe(200)
})

test('POST rank creates rank under department', async () => {
  ;(prisma.department.findFirst as jest.Mock).mockResolvedValue({ id: 'd1', communityId: 'c1' })
  ;(prisma.rank.create as jest.Mock).mockResolvedValue({ id: 'r1', name: 'Officer' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Officer', level: 1 }),
  })
  expect((await POST_RANK(req, adminCtx, deptParams)).status).toBe(201)
})
