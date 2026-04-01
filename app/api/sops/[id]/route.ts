import { NextResponse } from 'next/server'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import { checkFeatureAccess, FeatureGatedError } from '@/lib/plans'
import type { CommunityContext } from '@/lib/community-auth'

async function getHandler(req: Request, ctx: CommunityContext, id: string): Promise<Response> {
  try { checkFeatureAccess(ctx.community.planTier, 'sops') } catch (e) {
    if (e instanceof FeatureGatedError) return NextResponse.json({ error: e.message }, { status: 403 })
    throw e
  }
  const sop = await prisma.sOP.findFirst({ where: { id, communityId: ctx.communityId } })
  if (!sop) return NextResponse.json({ error: 'Not found' }, { status: 404 })
  return NextResponse.json({ sop })
}

export async function GET(
  req: Request,
  ctx: CommunityContext,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params
  return withCommunityAuth((r, c) => getHandler(r, c, id))(req, ctx)
}
