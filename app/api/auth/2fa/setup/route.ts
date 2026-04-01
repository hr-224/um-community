import { NextResponse } from 'next/server'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { generateTotpSecret } from '@/lib/totp'
import QRCode from 'qrcode'

export async function POST() {
  const session = await auth()
  if (!session?.user?.id) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })

  const { secret, otpauthUrl } = generateTotpSecret(session.user.email!)

  // Store temp secret (not enabled until verified)
  await prisma.user.update({
    where: { id: session.user.id },
    data: { totpSecret: secret },
  })

  const qrCodeDataUrl = await QRCode.toDataURL(otpauthUrl)
  return NextResponse.json({ qrCode: qrCodeDataUrl, secret })
}
