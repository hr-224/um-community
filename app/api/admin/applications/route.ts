import { NextResponse } from 'next/server'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

export const GET = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const url = new URL(req.url)
  const status = url.searchParams.get('status') ?? 'PENDING'

  const applications = await prisma.application.findMany({
    where: { communityId: ctx.communityId, status },
    orderBy: { createdAt: 'desc' },
    take: 50,
  })

  return NextResponse.json({ applications })
}, { adminOnly: true })
