import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const updateSchema = z.object({
  role: z.enum(['ADMIN', 'MODERATOR', 'MEMBER']).optional(),
  status: z.enum(['ACTIVE', 'INACTIVE', 'SUSPENDED']).optional(),
  departmentId: z.string().nullable().optional(),
  rankId: z.string().nullable().optional(),
  callsign: z.string().nullable().optional(),
})

async function patchHandler(req: Request, ctx: CommunityContext, id: string): Promise<Response> {
  const target = await prisma.communityMember.findFirst({
    where: { id, communityId: ctx.communityId },
  })
  if (!target) return NextResponse.json({ error: 'Not found' }, { status: 404 })
  if (target.role === 'OWNER') return NextResponse.json({ error: 'Cannot modify owner' }, { status: 400 })

  const body = await req.json()
  const parsed = updateSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const updated = await prisma.communityMember.update({
    where: { id, communityId: ctx.communityId },
    data: parsed.data,
  })

  return NextResponse.json({ member: updated })
}

async function deleteHandler(_req: Request, ctx: CommunityContext, id: string): Promise<Response> {
  const target = await prisma.communityMember.findFirst({
    where: { id, communityId: ctx.communityId },
  })
  if (!target) return NextResponse.json({ error: 'Not found' }, { status: 404 })
  if (target.role === 'OWNER') return NextResponse.json({ error: 'Cannot remove owner' }, { status: 400 })

  await prisma.communityMember.delete({ where: { id, communityId: ctx.communityId } })
  return NextResponse.json({ ok: true })
}

export async function PATCH(
  req: Request,
  ctx: CommunityContext,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params
  return withCommunityAuth((r, c) => patchHandler(r, c, id), { adminOnly: true })(req, ctx)
}

export async function DELETE(
  req: Request,
  ctx: CommunityContext,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params
  return withCommunityAuth((r, c) => deleteHandler(r, c, id), { adminOnly: true })(req, ctx)
}
