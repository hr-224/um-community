import { POST as forgotPost } from '@/app/api/auth/forgot-password/route'
import { POST as resetPost } from '@/app/api/auth/reset-password/route'
import { prisma } from '@/lib/prisma'

jest.mock('@/lib/prisma', () => ({
  prisma: {
    user: { findUnique: jest.fn(), update: jest.fn() },
    verificationToken: {
      create: jest.fn(),
      findUnique: jest.fn(),
      findFirst: jest.fn(),
      deleteMany: jest.fn(),
      delete: jest.fn(),
    },
    $transaction: jest.fn(),
  },
}))
jest.mock('@/lib/email', () => ({ sendEmail: jest.fn(), passwordResetEmailHtml: jest.fn(() => '') }))
jest.mock('@/lib/auth', () => ({
  ...jest.requireActual('@/lib/auth'),
  hashPassword: jest.fn(async (p: string) => `hashed:${p}`),
}))

const mock = prisma as jest.Mocked<typeof prisma>

beforeEach(() => jest.clearAllMocks())

test('forgot-password returns 200 even if user not found (no enumeration)', async () => {
  (mock.user.findUnique as jest.Mock).mockResolvedValue(null)
  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ email: 'notfound@test.com' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await forgotPost(req)
  expect(res.status).toBe(200)
})

test('reset-password returns 400 for expired token', async () => {
  ;(mock.verificationToken.findFirst as jest.Mock).mockResolvedValue({
    identifier: 'test@test.com',
    token: 'tok',
    type: 'PASSWORD_RESET',
    expires: new Date(Date.now() - 1000),
  })
  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ token: 'tok', email: 'test@test.com', password: 'newpassword123' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await resetPost(req)
  expect(res.status).toBe(400)
  const json = await res.json()
  expect(json.error).toMatch(/expired/i)
})
