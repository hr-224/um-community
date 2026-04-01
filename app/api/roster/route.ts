import { NextResponse } from 'next/server'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'
import type { MemberStatus } from '@/lib/generated/prisma/enums'

const PAGE_SIZE = 50

export const GET = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const url = new URL(req.url)
  const page = Math.max(1, parseInt(url.searchParams.get('page') ?? '1'))
  const departmentId = url.searchParams.get('departmentId') ?? undefined
  const status = (url.searchParams.get('status') ?? 'ACTIVE') as MemberStatus

  const where = {
    communityId: ctx.communityId,
    status,
    ...(departmentId ? { departmentId } : {}),
  }

  const [members, total] = await Promise.all([
    prisma.communityMember.findMany({
      where,
      include: {
        user: { select: { email: true, avatar: true } },
        department: { select: { name: true } },
        rank: { select: { name: true } },
      },
      orderBy: [{ role: 'asc' }, { joinedAt: 'asc' }],
      skip: (page - 1) * PAGE_SIZE,
      take: PAGE_SIZE,
    }),
    prisma.communityMember.count({ where }),
  ])

  return NextResponse.json({ members, total, page, pageSize: PAGE_SIZE })
})
