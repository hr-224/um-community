import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import { checkFeatureAccess, FeatureGatedError } from '@/lib/plans'
import type { CommunityContext } from '@/lib/community-auth'

const WRITE_ROLES = new Set(['OWNER', 'ADMIN', 'MODERATOR'])

const createSchema = z.object({
  title: z.string().min(1, 'Title required').max(200),
  startAt: z.string().datetime(),
  endAt: z.string().datetime(),
  slots: z.number().int().min(0).default(0),
})

function featureCheck(ctx: CommunityContext): Response | null {
  try { checkFeatureAccess(ctx.community.planTier, 'shifts') } catch (e) {
    if (e instanceof FeatureGatedError) return NextResponse.json({ error: e.message }, { status: 403 })
    throw e
  }
  return null
}

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const gate = featureCheck(ctx)
  if (gate) return gate
  const shifts = await prisma.shift.findMany({
    where: { communityId: ctx.communityId },
    orderBy: { startAt: 'asc' },
    take: 50,
  })
  return NextResponse.json({ shifts })
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const gate = featureCheck(ctx)
  if (gate) return gate
  if (!WRITE_ROLES.has(ctx.member.role)) return NextResponse.json({ error: 'Forbidden' }, { status: 403 })
  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  const shift = await prisma.shift.create({
    data: {
      communityId: ctx.communityId,
      title: parsed.data.title,
      startAt: new Date(parsed.data.startAt),
      endAt: new Date(parsed.data.endAt),
      slots: parsed.data.slots,
    },
  })
  return NextResponse.json({ shift }, { status: 201 })
})
