import * as bcrypt from 'bcryptjs'
import NextAuth from 'next-auth'
import type { NextAuthConfig } from 'next-auth'
import Credentials from 'next-auth/providers/credentials'
import Discord from 'next-auth/providers/discord'
import { PrismaAdapter } from '@auth/prisma-adapter'
import { prisma } from '@/lib/prisma'

// ---------------------------------------------------------------------------
// Password utilities
// ---------------------------------------------------------------------------

export async function hashPassword(password: string): Promise<string> {
  return bcrypt.hash(password, 12)
}

export async function verifyPassword(password: string, hash: string): Promise<boolean> {
  return bcrypt.compare(password, hash)
}

// ---------------------------------------------------------------------------
// NextAuth configuration
// ---------------------------------------------------------------------------

export const authConfig: NextAuthConfig = {
  adapter: PrismaAdapter(prisma),
  session: {
    strategy: 'jwt',
  },
  pages: {
    signIn: '/login',
    error: '/login',
  },
  providers: [
    Credentials({
      name: 'credentials',
      credentials: {
        email: { label: 'Email', type: 'email' },
        password: { label: 'Password', type: 'password' },
      },
      async authorize(credentials) {
        const email = credentials?.email
        const password = credentials?.password

        if (typeof email !== 'string' || typeof password !== 'string') {
          return null
        }

        const user = await prisma.user.findUnique({ where: { email } })
        if (!user || !user.passwordHash) {
          return null
        }

        const valid = await verifyPassword(password, user.passwordHash)
        if (!valid) {
          return null
        }

        return {
          id: user.id,
          email: user.email,
          name: user.discordUsername ?? undefined,
          image: user.avatar ?? undefined,
        }
      },
    }),
    Discord({
      clientId: process.env.DISCORD_CLIENT_ID ?? '',
      clientSecret: process.env.DISCORD_CLIENT_SECRET ?? '',
    }),
  ],
  callbacks: {
    async signIn({ user, account, profile }) {
      // For Discord OAuth: link to existing user by email if discordId not yet set
      if (account?.provider === 'discord' && profile?.email) {
        const email = profile.email as string
        const discordId = profile.id as string | undefined

        const existingUser = await prisma.user.findUnique({ where: { email } })
        if (existingUser && !existingUser.discordId && discordId) {
          await prisma.user.update({
            where: { email },
            data: {
              discordId,
              discordUsername: (profile.username as string | undefined) ?? undefined,
              avatar: (profile.avatar as string | undefined) ?? undefined,
            },
          })
        }
      }
      return true
    },
    async jwt({ token, user }) {
      if (user) {
        token.userId = user.id
        // Fetch isSuperAdmin from DB on first sign-in
        if (user.id) {
          const dbUser = await prisma.user.findUnique({ where: { id: user.id } })
          token.isSuperAdmin = dbUser?.isSuperAdmin ?? false
        }
      }
      return token
    },
    async session({ session, token }) {
      if (token) {
        session.user.id = token.userId as string
        session.user.isSuperAdmin = (token.isSuperAdmin as boolean) ?? false
      }
      return session
    },
  },
}

export const { handlers, auth, signIn, signOut } = NextAuth(authConfig)
