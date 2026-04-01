import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { NextResponse } from 'next/server'
import { cookies } from 'next/headers'
import type { Community, CommunityMember } from '@/lib/generated/prisma/client'

export interface CommunityContext {
  userId: string
  communityId: string
  community: Community
  member: CommunityMember & { community: Community }
}

type CommunityHandler = (req: Request, ctx: CommunityContext) => Promise<Response>

const ADMIN_ROLES = new Set(['OWNER', 'ADMIN'])

export function withCommunityAuth(
  handler: CommunityHandler,
  opts?: { adminOnly?: boolean }
): (req: Request) => Promise<Response> {
  return async (req: Request) => {
    const session = await auth()
    if (!session?.user?.id) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
    }
    const cookieStore = await cookies()
    const communityId = cookieStore.get('active_community_id')?.value
    if (!communityId) {
      return NextResponse.json({ error: 'No active community' }, { status: 400 })
    }
    const member = await prisma.communityMember.findFirst({
      where: { communityId, userId: session.user.id, status: 'ACTIVE' },
      include: { community: true },
    })
    if (!member) {
      return NextResponse.json({ error: 'Forbidden' }, { status: 403 })
    }
    if (opts?.adminOnly && !ADMIN_ROLES.has(member.role)) {
      return NextResponse.json({ error: 'Forbidden' }, { status: 403 })
    }
    return handler(req, { userId: session.user.id, communityId, community: member.community, member })
  }
}
