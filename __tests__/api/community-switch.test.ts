import { POST } from '@/app/api/community/switch/route'

jest.mock('@/lib/auth', () => ({ auth: jest.fn() }))
jest.mock('@/lib/prisma', () => ({
  prisma: { communityMember: { findFirst: jest.fn() } },
}))
jest.mock('next/headers', () => ({ cookies: jest.fn() }))

import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { cookies } from 'next/headers'

const mockAuth = auth as jest.Mock
const mockFindFirst = prisma.communityMember.findFirst as jest.Mock
const mockCookies = cookies as jest.Mock
const mockCookieStore = { set: jest.fn() }

beforeEach(() => { jest.clearAllMocks(); mockCookies.mockResolvedValue(mockCookieStore) })

function req(body: object) {
  return new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
  })
}

test('returns 401 if not authenticated', async () => {
  mockAuth.mockResolvedValue(null)
  expect((await POST(req({ communityId: 'c1' }))).status).toBe(401)
})

test('returns 400 if communityId missing', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  expect((await POST(req({}))).status).toBe(400)
})

test('returns 403 if not a member', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockFindFirst.mockResolvedValue(null)
  expect((await POST(req({ communityId: 'c1' }))).status).toBe(403)
})

test('sets cookie and returns ok when valid', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockFindFirst.mockResolvedValue({ id: 'm1' })
  const res = await POST(req({ communityId: 'c1' }))
  expect(res.status).toBe(200)
  expect(mockCookieStore.set).toHaveBeenCalledWith(
    'active_community_id', 'c1', expect.objectContaining({ httpOnly: true })
  )
})
