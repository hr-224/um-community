import { NextResponse } from 'next/server'
import { z } from 'zod'
import { prisma } from '@/lib/prisma'
import { sendEmail, passwordResetEmailHtml } from '@/lib/email'
import { nanoid } from 'nanoid'

export async function POST(req: Request) {
  try {
    const { email } = z.object({ email: z.string().email() }).parse(await req.json())
    const user = await prisma.user.findUnique({ where: { email } })

    // Always return 200 to prevent email enumeration
    if (!user) return NextResponse.json({ message: 'If that email exists, a reset link was sent.' })

    const token = nanoid(32)
    const expires = new Date(Date.now() + 60 * 60 * 1000) // 1h

    await prisma.verificationToken.create({ data: { identifier: email, token, expires } })

    const resetUrl = `${process.env.NEXT_PUBLIC_APP_URL}/reset-password?token=${token}&email=${encodeURIComponent(email)}`
    await sendEmail({ to: email, subject: 'Reset your password', html: passwordResetEmailHtml(resetUrl) })

    return NextResponse.json({ message: 'If that email exists, a reset link was sent.' })
  } catch {
    return NextResponse.json({ error: 'Something went wrong' }, { status: 500 })
  }
}
