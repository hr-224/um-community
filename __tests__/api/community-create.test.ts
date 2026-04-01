import { POST } from '@/app/api/community/create/route'

jest.mock('@/lib/auth', () => ({ auth: jest.fn() }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    community: { findFirst: jest.fn(), create: jest.fn() },
    communityMember: { create: jest.fn() },
  },
}))
jest.mock('@/lib/stripe', () => ({
  createCheckoutSession: jest.fn(async () => 'https://checkout.stripe.com/pay/cs_test'),
  getPriceIdForPlan: jest.fn(() => 'price_123'),
}))

import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'

const mockAuth = auth as jest.Mock
const mockPrisma = prisma as jest.Mocked<typeof prisma>

beforeEach(() => jest.clearAllMocks())

test('returns 401 if not authenticated', async () => {
  mockAuth.mockResolvedValue(null)
  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ name: 'My Community', planTier: 'FREE' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(401)
})

test('returns 400 for missing name', async () => {
  mockAuth.mockResolvedValue({ user: { id: '1', email: 'a@b.com' } })
  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ name: '', planTier: 'FREE' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(400)
})

test('creates FREE community and returns it directly', async () => {
  mockAuth.mockResolvedValue({ user: { id: '1', email: 'a@b.com' } })
  ;(mockPrisma.community.findFirst as jest.Mock).mockResolvedValue(null)
  ;(mockPrisma.community.create as jest.Mock).mockResolvedValue({ id: 'comm-1', name: 'Test', slug: 'test' })
  ;(mockPrisma.communityMember.create as jest.Mock).mockResolvedValue({})

  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ name: 'Test Community', planTier: 'FREE' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(201)
  const json = await res.json()
  expect(json.community).toBeDefined()
})

test('returns Stripe checkout URL for paid plans', async () => {
  mockAuth.mockResolvedValue({ user: { id: '1', email: 'a@b.com' } })
  ;(mockPrisma.community.findFirst as jest.Mock).mockResolvedValue(null)

  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ name: 'Test Community', planTier: 'STANDARD' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.checkoutUrl).toContain('stripe.com')
})
