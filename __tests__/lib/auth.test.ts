import { hashPassword, verifyPassword } from '@/lib/auth'

test('hashPassword returns a bcrypt hash', async () => {
  const hash = await hashPassword('mypassword123')
  expect(hash).toMatch(/^\$2[aby]\$/)
})

test('verifyPassword returns true for correct password', async () => {
  const hash = await hashPassword('mypassword123')
  expect(await verifyPassword('mypassword123', hash)).toBe(true)
})

test('verifyPassword returns false for wrong password', async () => {
  const hash = await hashPassword('mypassword123')
  expect(await verifyPassword('wrongpassword', hash)).toBe(false)
})
