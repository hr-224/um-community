import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const reviewSchema = z.object({
  action: z.enum(['approve', 'deny']),
})

async function patchHandler(req: Request, ctx: CommunityContext, id: string): Promise<Response> {
  const loa = await prisma.lOA.findFirst({ where: { id, communityId: ctx.communityId } })
  if (!loa) return NextResponse.json({ error: 'Not found' }, { status: 404 })
  const body = await req.json()
  const parsed = reviewSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  const status = parsed.data.action === 'approve' ? 'APPROVED' : 'DENIED'
  const updated = await prisma.lOA.update({
    where: { id, communityId: ctx.communityId },
    data: { status, approvedBy: ctx.userId },
  })
  return NextResponse.json({ loa: updated })
}

export async function PATCH(
  req: Request, ctx: CommunityContext,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params
  return withCommunityAuth((r, c) => patchHandler(r, c, id), { adminOnly: true })(req, ctx)
}
