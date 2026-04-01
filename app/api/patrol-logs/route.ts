import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import { checkFeatureAccess, FeatureGatedError } from '@/lib/plans'
import type { CommunityContext } from '@/lib/community-auth'

const createSchema = z.object({
  startTime: z.string().datetime(),
  endTime: z.string().datetime().optional(),
  departmentId: z.string().optional(),
  notes: z.string().max(2000).optional(),
})

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  try { checkFeatureAccess(ctx.community.planTier, 'patrolLogs') } catch (e) {
    if (e instanceof FeatureGatedError) return NextResponse.json({ error: e.message }, { status: 403 })
    throw e
  }
  const patrolLogs = await prisma.patrolLog.findMany({
    where: { communityId: ctx.communityId },
    orderBy: { startTime: 'desc' },
    take: 50,
  })
  return NextResponse.json({ patrolLogs })
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  try { checkFeatureAccess(ctx.community.planTier, 'patrolLogs') } catch (e) {
    if (e instanceof FeatureGatedError) return NextResponse.json({ error: e.message }, { status: 403 })
    throw e
  }
  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })

  const patrolLog = await prisma.patrolLog.create({
    data: {
      communityId: ctx.communityId,
      memberId: ctx.userId,
      startTime: new Date(parsed.data.startTime),
      endTime: parsed.data.endTime ? new Date(parsed.data.endTime) : undefined,
      departmentId: parsed.data.departmentId,
      notes: parsed.data.notes,
    },
  })
  return NextResponse.json({ patrolLog }, { status: 201 })
})
