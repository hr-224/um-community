import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const createSchema = z.object({
  name: z.string().min(1, 'Name required').max(80),
  level: z.number().int().min(0).default(0),
  isCommand: z.boolean().optional().default(false),
})

async function postHandler(req: Request, ctx: CommunityContext, departmentId: string) {
  const dept = await prisma.department.findFirst({ where: { id: departmentId, communityId: ctx.communityId } })
  if (!dept) return NextResponse.json({ error: 'Not found' }, { status: 404 })

  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })

  const rank = await prisma.rank.create({
    data: { communityId: ctx.communityId, departmentId, ...parsed.data },
  })
  return NextResponse.json({ rank }, { status: 201 })
}

export async function POST(req: Request, ctx: CommunityContext, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return withCommunityAuth((r, c) => postHandler(r, c, id), { adminOnly: true })(req, ctx)
}
