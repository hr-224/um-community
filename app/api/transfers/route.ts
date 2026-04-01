import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import { checkFeatureAccess, FeatureGatedError } from '@/lib/plans'
import type { CommunityContext } from '@/lib/community-auth'

const createSchema = z.object({
  toDeptId: z.string().min(1, 'Target department required'),
  reason: z.string().min(1, 'Reason required').max(1000),
})

function featureCheck(ctx: CommunityContext): Response | null {
  try { checkFeatureAccess(ctx.community.planTier, 'transfers') } catch (e) {
    if (e instanceof FeatureGatedError) return NextResponse.json({ error: e.message }, { status: 403 })
    throw e
  }
  return null
}

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const gate = featureCheck(ctx)
  if (gate) return gate
  const transfers = await prisma.transfer.findMany({
    where: { communityId: ctx.communityId },
    orderBy: { createdAt: 'desc' },
    take: 50,
  })
  return NextResponse.json({ transfers })
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
  const toDept = await prisma.department.findFirst({
    where: { id: parsed.data.toDeptId, communityId: ctx.communityId },
  })
  if (!toDept) return NextResponse.json({ error: 'Department not found' }, { status: 404 })
  if (member.departmentId === parsed.data.toDeptId) {
    return NextResponse.json({ error: 'Already in that department' }, { status: 400 })
  }
  const transfer = await prisma.transfer.create({
    data: {
      communityId: ctx.communityId,
      memberId: member.id,
      fromDeptId: member.departmentId ?? parsed.data.toDeptId,
      toDeptId: parsed.data.toDeptId,
      reason: parsed.data.reason,
    },
  })
  return NextResponse.json({ transfer }, { status: 201 })
})
