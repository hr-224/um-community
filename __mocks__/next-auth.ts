const NextAuth = (_config: unknown) => ({
  handlers: { GET: jest.fn(), POST: jest.fn() },
  auth: jest.fn(),
  signIn: jest.fn(),
  signOut: jest.fn(),
})

export default NextAuth
