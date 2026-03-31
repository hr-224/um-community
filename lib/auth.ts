import * as bcrypt from 'bcryptjs'
import NextAuth from 'next-auth'
import type { NextAuthConfig } from 'next-auth'
import Credentials from 'next-auth/providers/credentials'
import Discord from 'next-auth/providers/discord'
import { PrismaAdapter } from '@auth/prisma-adapter'
import { prisma } from '@/lib/prisma'
import { z } from 'zod'

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
        const loginSchema = z.object({
          email: z.string().email(),
          password: z.string().min(1),
        })

        const parsed = loginSchema.safeParse(credentials)
        if (!parsed.success) return null

        const { email, password } = parsed.data

        const user = await prisma.user.findUnique({ where: { email } })
        if (!user || !user.passwordHash) {
          return null
        }

        if (!user.emailVerified) return null

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
      clientId: process.env.DISCORD_CLIENT_ID!,
      clientSecret: process.env.DISCORD_CLIENT_SECRET!,
      profile(profile) {
        return {
          id: profile.id,
          email: profile.email,
          name: profile.username,
          image: profile.avatar
            ? `https://cdn.discordapp.com/avatars/${profile.id}/${profile.avatar}.png`
            : null,
          discordId: profile.id,
          discordUsername: profile.username,
        }
      },
    }),
  ],
  callbacks: {
    async signIn({ user, account }) {
      // For Discord OAuth: link to existing user by email if discordId not yet set
      if (account?.provider === 'discord' && user?.email) {
        const email = user.email

        const existing = await prisma.user.findUnique({ where: { email } })
        if (existing && !existing.discordId) {
          await prisma.user.update({
            where: { email },
            data: {
              discordId: (user as { discordId?: string }).discordId,
              discordUsername: (user as { discordUsername?: string }).discordUsername,
              avatar: user.image ?? existing.avatar,
              emailVerified: existing.emailVerified ?? new Date(),
            },
          })
        }
      }
      return true
    },
    async jwt({ token, user }) {
      if (user) {
        token.userId = user.id
        token.isSuperAdmin = (user as { isSuperAdmin?: boolean }).isSuperAdmin ?? false
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
