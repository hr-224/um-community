import { NextResponse } from 'next/server'
import { z } from 'zod'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { verifyTotpToken } from '@/lib/totp'

export async function POST(req: Request) {
  const session = await auth()
  if (!session?.user?.id) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })

  const { token } = z.object({ token: z.string().length(6) }).parse(await req.json())

  const user = await prisma.user.findUnique({ where: { id: session.user.id } })
  if (!user?.totpSecret) return NextResponse.json({ error: 'No 2FA setup in progress' }, { status: 400 })

  const valid = verifyTotpToken(user.totpSecret, token)
  if (!valid) return NextResponse.json({ error: 'Invalid code' }, { status: 400 })

  await prisma.user.update({ where: { id: user.id }, data: { totpEnabled: true } })
  return NextResponse.json({ message: '2FA enabled' })
}
