import { NextResponse } from 'next/server'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const { communityId } = ctx

  const [memberCount, pendingApplications, departments] = await Promise.all([
    prisma.communityMember.count({ where: { communityId, status: 'ACTIVE' } }),
    prisma.application.count({ where: { communityId, status: 'PENDING' } }),
    prisma.department.findMany({
      where: { communityId },
      include: { _count: { select: { members: true } } },
      orderBy: { sortOrder: 'asc' },
    }),
  ])

  return NextResponse.json({ memberCount, pendingApplications, departments })
})
