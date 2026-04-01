import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import { checkFeatureAccess, FeatureGatedError } from '@/lib/plans'
import type { CommunityContext } from '@/lib/community-auth'

const createSchema = z.object({
  name: z.string().min(1, 'Name required').max(200),
  fileUrl: z.string().url('Must be a valid URL'),
  category: z.string().optional(),
})

function featureCheck(ctx: CommunityContext): Response | null {
  try { checkFeatureAccess(ctx.community.planTier, 'documents') } catch (e) {
    if (e instanceof FeatureGatedError) return NextResponse.json({ error: e.message }, { status: 403 })
    throw e
  }
  return null
}

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const gate = featureCheck(ctx)
  if (gate) return gate
  const documents = await prisma.document.findMany({
    where: { communityId: ctx.communityId },
    orderBy: { createdAt: 'desc' },
    take: 100,
  })
  return NextResponse.json({ documents })
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const gate = featureCheck(ctx)
  if (gate) return gate
  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  const document = await prisma.document.create({
    data: { communityId: ctx.communityId, uploadedBy: ctx.userId, ...parsed.data },
  })
  return NextResponse.json({ document }, { status: 201 })
})
