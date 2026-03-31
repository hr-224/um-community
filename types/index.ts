import { DefaultSession } from 'next-auth'

declare module 'next-auth' {
  interface Session {
    user: DefaultSession['user'] & {
      id: string
      isSuperAdmin: boolean
    }
  }
}

declare module '@auth/core/jwt' {
  interface JWT {
    userId?: string
    isSuperAdmin?: boolean
  }
}
