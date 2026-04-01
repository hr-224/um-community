import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import { checkPlanLimit, PlanLimitError } from '@/lib/plans'
import type { CommunityContext } from '@/lib/community-auth'

const createSchema = z.object({
  name: z.string().min(1, 'Name required').max(80),
  description: z.string().optional(),
  color: z.string().optional(),
  sortOrder: z.number().optional().default(0),
})

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const departments = await prisma.department.findMany({
    where: { communityId: ctx.communityId },
    include: { ranks: { orderBy: { level: 'asc' } } },
    orderBy: { sortOrder: 'asc' },
  })
  return NextResponse.json({ departments })
}, { adminOnly: true })

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const existing = await prisma.department.findMany({ where: { communityId: ctx.communityId } })

  try {
    checkPlanLimit(ctx.community.planTier, 'departments', existing.length)
  } catch (e) {
    if (e instanceof PlanLimitError) {
      return NextResponse.json({ error: e.message }, { status: 403 })
    }
    throw e
  }

  const department = await prisma.department.create({
    data: { communityId: ctx.communityId, ...parsed.data },
  })

  return NextResponse.json({ department }, { status: 201 })
}, { adminOnly: true })
