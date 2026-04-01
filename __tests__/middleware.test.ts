import { middleware } from '@/middleware'
import { NextRequest } from 'next/server'

// Mock next-auth
jest.mock('@/lib/auth', () => ({
  auth: jest.fn(),
}))

import { auth } from '@/lib/auth'
const mockAuth = auth as jest.Mock

function makeReq(pathname: string) {
  return new NextRequest(`http://localhost${pathname}`)
}

test('redirects unauthenticated user from /dashboard to /login', async () => {
  mockAuth.mockResolvedValue(null)
  const res = await middleware(makeReq('/dashboard'))
  expect(res.status).toBe(307)
  expect(res.headers.get('location')).toContain('/login')
})

test('allows unauthenticated access to /login', async () => {
  mockAuth.mockResolvedValue(null)
  const res = await middleware(makeReq('/login'))
  // next() — no redirect
  expect(res.status).not.toBe(307)
})

test('redirects authenticated user away from /login to /dashboard', async () => {
  mockAuth.mockResolvedValue({ user: { id: '1' } })
  const res = await middleware(makeReq('/login'))
  expect(res.status).toBe(307)
  expect(res.headers.get('location')).toContain('/dashboard')
})
