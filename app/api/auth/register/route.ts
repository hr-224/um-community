import { NextResponse } from 'next/server'
import { z } from 'zod'
import { prisma } from '@/lib/prisma'
import { hashPassword } from '@/lib/auth'
import { sendEmail, verifyEmailHtml } from '@/lib/email'
import { nanoid } from 'nanoid'

const registerSchema = z.object({
  email: z.string().email(),
  password: z.string().min(8, 'Password must be at least 8 characters'),
})

export async function POST(req: Request) {
  try {
    const body = await req.json()
    const parsed = registerSchema.safeParse(body)
    if (!parsed.success) {
      return NextResponse.json(
        { error: parsed.error.issues[0].message },
        { status: 400 }
      )
    }

    const { email, password } = parsed.data

    const existing = await prisma.user.findUnique({ where: { email } })
    if (existing) {
      return NextResponse.json({ error: 'Email already in use' }, { status: 400 })
    }

    const passwordHash = await hashPassword(password)
    const verifyToken = nanoid(32)
    const expires = new Date(Date.now() + 24 * 60 * 60 * 1000) // 24h

    await prisma.user.create({
      data: { email, passwordHash, avatar: null },
    })

    await prisma.verificationToken.create({
      data: { identifier: email, token: verifyToken, expires },
    })

    const verifyUrl = `${process.env.NEXT_PUBLIC_APP_URL}/api/auth/verify-email?token=${verifyToken}&email=${encodeURIComponent(email)}`
    await sendEmail({ to: email, subject: 'Verify your email', html: verifyEmailHtml(verifyUrl) })

    return NextResponse.json({ message: 'Check your email to verify your account' }, { status: 201 })
  } catch {
    return NextResponse.json({ error: 'Something went wrong' }, { status: 500 })
  }
}
