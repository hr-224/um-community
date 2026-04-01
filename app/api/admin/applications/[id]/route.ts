import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const reviewSchema = z.object({
  action: z.enum(['approve', 'deny']),
  notes: z.string().optional(),
})

async function patchHandler(
  req: Request,
  ctx: CommunityContext,
  id: string
): Promise<Response> {
  const body = await req.json()
  const parsed = reviewSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const application = await prisma.application.findFirst({
    where: { id, communityId: ctx.communityId },
  })
  if (!application) return NextResponse.json({ error: 'Not found' }, { status: 404 })

  const status = parsed.data.action === 'approve' ? 'APPROVED' : 'DENIED'
  const updated = await prisma.application.update({
    where: { id },
    data: { status, reviewedBy: ctx.userId, reviewedAt: new Date(), notes: parsed.data.notes },
  })

  return NextResponse.json({ application: updated })
}

export async function PATCH(
  req: Request,
  ctx: CommunityContext,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params
  const wrappedHandler = withCommunityAuth(
    (r: Request, c: CommunityContext) => patchHandler(r, c, id),
    { adminOnly: true }
  )
  return wrappedHandler(req, ctx)
}
