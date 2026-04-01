import { NextResponse } from 'next/server'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { cookies } from 'next/headers'

export async function POST(req: Request) {
  const session = await auth()
  if (!session?.user?.id) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })

  const body = await req.json()
  const communityId: string | undefined = body?.communityId
  if (!communityId) return NextResponse.json({ error: 'communityId required' }, { status: 400 })

  const member = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!member) return NextResponse.json({ error: 'Forbidden' }, { status: 403 })

  const cookieStore = await cookies()
  cookieStore.set('active_community_id', communityId, {
    httpOnly: true, sameSite: 'lax', path: '/', maxAge: 60 * 60 * 24 * 30,
  })

  return NextResponse.json({ ok: true })
}
