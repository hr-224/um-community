import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import { checkFeatureAccess, FeatureGatedError } from '@/lib/plans'
import type { CommunityContext } from '@/lib/community-auth'

const WRITE_ROLES = new Set(['OWNER', 'ADMIN', 'MODERATOR'])

const createSchema = z.object({
  title: z.string().min(1, 'Title required').max(200),
  content: z.string().min(1, 'Content required'),
  version: z.string().default('1.0'),
})

function featureCheck(ctx: CommunityContext): Response | null {
  try { checkFeatureAccess(ctx.community.planTier, 'sops') } catch (e) {
    if (e instanceof FeatureGatedError) return NextResponse.json({ error: e.message }, { status: 403 })
    throw e
  }
  return null
}

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const gate = featureCheck(ctx)
  if (gate) return gate
  const sops = await prisma.sOP.findMany({
    where: { communityId: ctx.communityId },
    select: { id: true, title: true, version: true, publishedAt: true, authorId: true },
    orderBy: { publishedAt: 'desc' },
    take: 100,
  })
  return NextResponse.json({ sops })
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const gate = featureCheck(ctx)
  if (gate) return gate
  if (!WRITE_ROLES.has(ctx.member.role)) return NextResponse.json({ error: 'Forbidden' }, { status: 403 })
  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  const sop = await prisma.sOP.create({
    data: { communityId: ctx.communityId, authorId: ctx.userId, ...parsed.data },
  })
  return NextResponse.json({ sop }, { status: 201 })
})
