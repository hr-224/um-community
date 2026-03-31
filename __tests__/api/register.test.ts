import { POST } from '@/app/api/auth/register/route'
import { prisma } from '@/lib/prisma'

jest.mock('@/lib/prisma', () => ({
  prisma: {
    user: {
      findUnique: jest.fn(),
      create: jest.fn(),
    },
    verificationToken: {
      create: jest.fn(),
    },
  },
}))
jest.mock('@/lib/email', () => ({ sendEmail: jest.fn(), verifyEmailHtml: jest.fn(() => '') }))

const mockPrisma = prisma as jest.Mocked<typeof prisma>

beforeEach(() => jest.clearAllMocks())

test('returns 400 when email already exists', async () => {
  (mockPrisma.user.findUnique as jest.Mock).mockResolvedValue({ id: '1' })
  const req = new Request('http://localhost/api/auth/register', {
    method: 'POST',
    body: JSON.stringify({ email: 'test@test.com', password: 'password123' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(400)
  const json = await res.json()
  expect(json.error).toMatch(/already/i)
})

test('returns 201 on successful registration', async () => {
  (mockPrisma.user.findUnique as jest.Mock).mockResolvedValue(null)
  ;(mockPrisma.user.create as jest.Mock).mockResolvedValue({ id: 'new-user', email: 'test@test.com' })
  const req = new Request('http://localhost/api/auth/register', {
    method: 'POST',
    body: JSON.stringify({ email: 'new@test.com', password: 'password123' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(201)
})

test('returns 400 for invalid email', async () => {
  const req = new Request('http://localhost/api/auth/register', {
    method: 'POST',
    body: JSON.stringify({ email: 'not-an-email', password: 'password123' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(400)
})

test('returns 400 for password under 8 chars', async () => {
  const req = new Request('http://localhost/api/auth/register', {
    method: 'POST',
    body: JSON.stringify({ email: 'test@test.com', password: 'short' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(400)
})
