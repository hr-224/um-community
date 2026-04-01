import { GET, PATCH } from '@/app/api/account/route'

jest.mock('@/lib/auth', () => ({ auth: jest.fn() }))
jest.mock('@/lib/prisma', () => ({
  prisma: { user: { findUnique: jest.fn(), update: jest.fn() } },
}))

import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'

const mockAuth = auth as jest.Mock

beforeEach(() => jest.clearAllMocks())

test('GET returns 401 if not authenticated', async () => {
  mockAuth.mockResolvedValue(null)
  expect((await GET(new Request('http://localhost'))).status).toBe(401)
})

test('GET returns user profile', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  ;(prisma.user.findUnique as jest.Mock).mockResolvedValue({ id: 'u1', email: 'test@example.com', avatar: null, discordUsername: null })
  const res = await GET(new Request('http://localhost'))
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.user.email).toBe('test@example.com')
})

test('PATCH updates avatar URL', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  ;(prisma.user.update as jest.Mock).mockResolvedValue({ id: 'u1', email: 'test@example.com', avatar: 'https://example.com/a.png' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ avatar: 'https://example.com/a.png' }),
  })
  expect((await PATCH(req)).status).toBe(200)
})

test('PATCH returns 400 for invalid avatar URL', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ avatar: 'not-a-url' }),
  })
  expect((await PATCH(req)).status).toBe(400)
})
