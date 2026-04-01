import { POST } from '@/app/api/community/join/route'

jest.mock('@/lib/auth', () => ({ auth: jest.fn() }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    inviteLink: { findFirst: jest.fn(), update: jest.fn() },
    communityMember: { findFirst: jest.fn(), create: jest.fn() },
    community: { findUnique: jest.fn() },
    application: { create: jest.fn() },
  },
}))

import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'

const mockAuth = auth as jest.Mock
const mockInvite = prisma.inviteLink.findFirst as jest.Mock
const mockMemberFind = prisma.communityMember.findFirst as jest.Mock
const mockMemberCreate = prisma.communityMember.create as jest.Mock
const mockCommunity = prisma.community.findUnique as jest.Mock
const mockAppCreate = prisma.application.create as jest.Mock

const validInvite = {
  id: 'inv1', code: 'abc123', communityId: 'c1', type: 'DIRECT_ADMIT',
  isActive: true, maxUses: null, useCount: 0, expiresAt: null,
}
const publicCommunity = { id: 'c1', isPublic: true, autoApproveMembers: true }

beforeEach(() => jest.clearAllMocks())

function req(body: object) {
  return new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
  })
}

test('returns 401 if not authenticated', async () => {
  mockAuth.mockResolvedValue(null)
  expect((await POST(req({ code: 'abc123' }))).status).toBe(401)
})

test('returns 400 if code missing', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  expect((await POST(req({}))).status).toBe(400)
})

test('returns 404 if invite not found', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockInvite.mockResolvedValue(null)
  expect((await POST(req({ code: 'bad' }))).status).toBe(404)
})

test('returns 409 if already a member', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockInvite.mockResolvedValue(validInvite)
  mockMemberFind.mockResolvedValue({ id: 'm1' })
  expect((await POST(req({ code: 'abc123' }))).status).toBe(409)
})

test('DIRECT_ADMIT invite creates ACTIVE member directly', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockInvite.mockResolvedValue(validInvite)
  mockMemberFind.mockResolvedValue(null)
  mockMemberCreate.mockResolvedValue({ id: 'm1', status: 'ACTIVE' })
  ;(prisma.inviteLink.update as jest.Mock).mockResolvedValue({})
  const res = await POST(req({ code: 'abc123' }))
  expect(res.status).toBe(201)
  expect(mockMemberCreate).toHaveBeenCalledWith(
    expect.objectContaining({ data: expect.objectContaining({ status: 'ACTIVE' }) })
  )
})

test('STANDARD invite on public+autoApprove community creates ACTIVE member', async () => {
  const standardInvite = { ...validInvite, type: 'STANDARD' }
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockInvite.mockResolvedValue(standardInvite)
  mockMemberFind.mockResolvedValue(null)
  mockCommunity.mockResolvedValue(publicCommunity)
  mockMemberCreate.mockResolvedValue({ id: 'm1', status: 'ACTIVE' })
  ;(prisma.inviteLink.update as jest.Mock).mockResolvedValue({})
  const res = await POST(req({ code: 'abc123' }))
  expect(res.status).toBe(201)
})

test('STANDARD invite on private community creates application', async () => {
  const standardInvite = { ...validInvite, type: 'STANDARD' }
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockInvite.mockResolvedValue(standardInvite)
  mockMemberFind.mockResolvedValue(null)
  mockCommunity.mockResolvedValue({ id: 'c1', isPublic: false, autoApproveMembers: false })
  mockAppCreate.mockResolvedValue({ id: 'app1', status: 'PENDING' })
  ;(prisma.inviteLink.update as jest.Mock).mockResolvedValue({})
  const res = await POST(req({ code: 'abc123' }))
  expect(res.status).toBe(202)
})
