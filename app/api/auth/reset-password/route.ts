import { NextResponse } from 'next/server'
import { z } from 'zod'
import { prisma } from '@/lib/prisma'
import { hashPassword } from '@/lib/auth'

const schema = z.object({
  token: z.string(),
  email: z.string().email(),
  password: z.string().min(8),
})

export async function POST(req: Request) {
  try {
    const { token, email, password } = schema.parse(await req.json())

    const record = await prisma.verificationToken.findUnique({ where: { token } })
    if (!record || record.identifier !== email) {
      return NextResponse.json({ error: 'Invalid or expired reset link' }, { status: 400 })
    }
    if (record.expires < new Date()) {
      await prisma.verificationToken.delete({ where: { token } })
      return NextResponse.json({ error: 'Reset link has expired' }, { status: 400 })
    }

    const passwordHash = await hashPassword(password)
    await prisma.user.update({ where: { email }, data: { passwordHash } })
    await prisma.verificationToken.delete({ where: { token } })

    return NextResponse.json({ message: 'Password updated successfully' })
  } catch {
    return NextResponse.json({ error: 'Something went wrong' }, { status: 500 })
  }
}
