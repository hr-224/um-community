import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const updateSchema = z.object({
  name: z.string().min(1).max(80).optional(),
  description: z.string().optional(),
  color: z.string().optional(),
  sortOrder: z.number().optional(),
})

async function patchHandler(req: Request, ctx: CommunityContext, id: string) {
  const dept = await prisma.department.findFirst({ where: { id, communityId: ctx.communityId } })
  if (!dept) return NextResponse.json({ error: 'Not found' }, { status: 404 })

  const body = await req.json()
  const parsed = updateSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })

  const updated = await prisma.department.update({ where: { id, communityId: ctx.communityId }, data: parsed.data })
  return NextResponse.json({ department: updated })
}

async function deleteHandler(_req: Request, ctx: CommunityContext, id: string) {
  const dept = await prisma.department.findFirst({ where: { id, communityId: ctx.communityId } })
  if (!dept) return NextResponse.json({ error: 'Not found' }, { status: 404 })

  const memberCount = await prisma.communityMember.count({ where: { departmentId: id } })
  if (memberCount > 0) {
    return NextResponse.json({ error: 'Cannot delete department with members' }, { status: 400 })
  }

  await prisma.department.delete({ where: { id, communityId: ctx.communityId } })
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
