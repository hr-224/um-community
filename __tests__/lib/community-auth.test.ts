import { withCommunityAuth } from '@/lib/community-auth'
import { NextResponse } from 'next/server'

jest.mock('@/lib/auth', () => ({ auth: jest.fn() }))
jest.mock('next/headers', () => ({ cookies: jest.fn() }))
jest.mock('@/lib/prisma', () => ({
  prisma: { communityMember: { findFirst: jest.fn() } },
}))

import { auth } from '@/lib/auth'
import { cookies } from 'next/headers'
import { prisma } from '@/lib/prisma'

const mockAuth = auth as jest.Mock
const mockCookies = cookies as jest.Mock
const mockFindFirst = prisma.communityMember.findFirst as jest.Mock

const fakeMember = {
  id: 'm1', communityId: 'c1', userId: 'u1',
  role: 'OWNER', status: 'ACTIVE',
  community: { id: 'c1', name: 'Test', planTier: 'FREE', status: 'ACTIVE', slug: 'test', logo: null },
}
const cookieStore = (val?: string) => ({
  get: (k: string) => k === 'active_community_id' && val ? { value: val } : undefined,
})

beforeEach(() => jest.clearAllMocks())

test('returns 401 when no session', async () => {
  mockAuth.mockResolvedValue(null)
  const res = await withCommunityAuth(jest.fn())(new Request('http://localhost'))
  expect(res.status).toBe(401)
})

test('returns 400 when no active_community_id cookie', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockCookies.mockResolvedValue(cookieStore())
  const res = await withCommunityAuth(jest.fn())(new Request('http://localhost'))
  expect(res.status).toBe(400)
})

test('returns 403 when not a member', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockCookies.mockResolvedValue(cookieStore('c1'))
  mockFindFirst.mockResolvedValue(null)
  const res = await withCommunityAuth(jest.fn())(new Request('http://localhost'))
  expect(res.status).toBe(403)
})

test('calls handler with correct context', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockCookies.mockResolvedValue(cookieStore('c1'))
  mockFindFirst.mockResolvedValue(fakeMember)
  const handler = jest.fn().mockResolvedValue(NextResponse.json({ ok: true }))
  await withCommunityAuth(handler)(new Request('http://localhost'))
  expect(handler).toHaveBeenCalledWith(
    expect.any(Request),
    { userId: 'u1', communityId: 'c1', community: fakeMember.community, member: fakeMember }
  )
})

test('returns 403 when adminOnly and role is MEMBER', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockCookies.mockResolvedValue(cookieStore('c1'))
  mockFindFirst.mockResolvedValue({ ...fakeMember, role: 'MEMBER' })
  const handler = jest.fn()
  const res = await withCommunityAuth(handler, { adminOnly: true })(new Request('http://localhost'))
  expect(res.status).toBe(403)
  expect(handler).not.toHaveBeenCalled()
})

test('returns 403 when adminOnly and role is MODERATOR', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockCookies.mockResolvedValue(cookieStore('c1'))
  mockFindFirst.mockResolvedValue({ ...fakeMember, role: 'MODERATOR' })
  const handler = jest.fn()
  const res = await withCommunityAuth(handler, { adminOnly: true })(new Request('http://localhost'))
  expect(res.status).toBe(403)
  expect(handler).not.toHaveBeenCalled()
})
