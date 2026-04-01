import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const updateSchema = z.object({
  name: z.string().min(1).max(80).optional(),
  level: z.number().int().min(0).optional(),
  isCommand: z.boolean().optional(),
})

async function patchHandler(req: Request, ctx: CommunityContext, id: string) {
  const rank = await prisma.rank.findFirst({ where: { id, communityId: ctx.communityId } })
  if (!rank) return NextResponse.json({ error: 'Not found' }, { status: 404 })
  const body = await req.json()
  const parsed = updateSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  const updated = await prisma.rank.update({ where: { id, communityId: ctx.communityId }, data: parsed.data })
  return NextResponse.json({ rank: updated })
}

async function deleteHandler(_req: Request, ctx: CommunityContext, id: string) {
  const rank = await prisma.rank.findFirst({ where: { id, communityId: ctx.communityId } })
  if (!rank) return NextResponse.json({ error: 'Not found' }, { status: 404 })
  await prisma.rank.delete({ where: { id, communityId: ctx.communityId } })
  return NextResponse.json({ ok: true })
}

export async function PATCH(req: Request, ctx: CommunityContext, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return withCommunityAuth((r, c) => patchHandler(r, c, id), { adminOnly: true })(req, ctx)
}

export async function DELETE(req: Request, ctx: CommunityContext, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return withCommunityAuth((r, c) => deleteHandler(r, c, id), { adminOnly: true })(req, ctx)
}
