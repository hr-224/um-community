import { GET } from '@/app/api/admin/applications/route'
import { PATCH } from '@/app/api/admin/applications/[id]/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler, opts) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { application: { findMany: jest.fn(), update: jest.fn(), findFirst: jest.fn() } },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1' } as any, member: { role: 'ADMIN' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('GET returns pending applications', async () => {
  ;(prisma.application.findMany as jest.Mock).mockResolvedValue([
    { id: 'app1', status: 'PENDING', applicantUserId: 'u2', formData: {} },
  ])
  const res = await GET(new Request('http://localhost'), adminCtx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.applications).toHaveLength(1)
})

test('PATCH approves an application', async () => {
  ;(prisma.application.findFirst as jest.Mock).mockResolvedValue({ id: 'app1', communityId: 'c1', status: 'PENDING' })
  ;(prisma.application.update as jest.Mock).mockResolvedValue({ id: 'app1', status: 'APPROVED' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'approve' }),
  })
  const res = await PATCH(req, adminCtx, { params: Promise.resolve({ id: 'app1' }) })
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.application.status).toBe('APPROVED')
})

test('PATCH denies an application with notes', async () => {
  ;(prisma.application.findFirst as jest.Mock).mockResolvedValue({ id: 'app1', communityId: 'c1', status: 'PENDING' })
  ;(prisma.application.update as jest.Mock).mockResolvedValue({ id: 'app1', status: 'DENIED' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'deny', notes: 'Not eligible' }),
  })
  const res = await PATCH(req, adminCtx, { params: Promise.resolve({ id: 'app1' }) })
  expect(res.status).toBe(200)
})

test('PATCH returns 404 if application not found', async () => {
  ;(prisma.application.findFirst as jest.Mock).mockResolvedValue(null)
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'approve' }),
  })
  const res = await PATCH(req, adminCtx, { params: Promise.resolve({ id: 'missing' }) })
  expect(res.status).toBe(404)
})
