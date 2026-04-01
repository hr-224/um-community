import { NextResponse } from 'next/server'
import { prisma } from '@/lib/prisma'

export async function GET(req: Request) {
  const { searchParams } = new URL(req.url)
  const token = searchParams.get('token')
  const email = searchParams.get('email')

  if (!token || !email) return NextResponse.redirect(`${process.env.NEXT_PUBLIC_APP_URL}/login?error=invalid`)

  const record = await prisma.verificationToken.findFirst({
    where: { token, type: 'EMAIL_VERIFY' },
  })
  if (!record || record.identifier !== email || record.expires < new Date()) {
    return NextResponse.redirect(`${process.env.NEXT_PUBLIC_APP_URL}/login?error=expired`)
  }

  await prisma.user.update({ where: { email }, data: { emailVerified: new Date() } })
  await prisma.verificationToken.delete({ where: { token } })

  return NextResponse.redirect(`${process.env.NEXT_PUBLIC_APP_URL}/login?verified=1`)
}
