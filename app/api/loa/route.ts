import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import { checkFeatureAccess, FeatureGatedError } from '@/lib/plans'
import type { CommunityContext } from '@/lib/community-auth'

const createSchema = z.object({
  startDate: z.string().datetime().or(z.string().regex(/^\d{4}-\d{2}-\d{2}$/)),
  endDate: z.string().datetime().or(z.string().regex(/^\d{4}-\d{2}-\d{2}$/)),
  reason: z.string().min(1, 'Reason required').max(1000),
})

function featureCheck(ctx: CommunityContext): Response | null {
  try { checkFeatureAccess(ctx.community.planTier, 'loa') } catch (e) {
    if (e instanceof FeatureGatedError) return NextResponse.json({ error: e.message }, { status: 403 })
    throw e
  }
  return null
}

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const gate = featureCheck(ctx)
  if (gate) return gate
  const loas = await prisma.lOA.findMany({
    where: { communityId: ctx.communityId },
    orderBy: { createdAt: 'desc' },
    take: 50,
  })
  return NextResponse.json({ loas })
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const gate = featureCheck(ctx)
  if (gate) return gate
  const member = await prisma.communityMember.findFirst({
    where: { communityId: ctx.communityId, userId: ctx.userId, status: 'ACTIVE' },
  })
  if (!member) return NextResponse.json({ error: 'Member not found' }, { status: 404 })
  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  const loa = await prisma.lOA.create({
    data: {
      communityId: ctx.communityId,
      memberId: member.id,
      startDate: new Date(parsed.data.startDate),
      endDate: new Date(parsed.data.endDate),
      reason: parsed.data.reason,
    },
  })
  return NextResponse.json({ loa }, { status: 201 })
})
