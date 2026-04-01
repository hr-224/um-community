import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const WRITE_ROLES = new Set(['OWNER', 'ADMIN', 'MODERATOR'])

const createSchema = z.object({
  title: z.string().min(1, 'Title required').max(200),
  description: z.string().optional(),
  startAt: z.string().datetime(),
  endAt: z.string().datetime().optional(),
  location: z.string().optional(),
})

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const events = await prisma.event.findMany({
    where: { communityId: ctx.communityId },
    orderBy: { startAt: 'asc' },
    take: 50,
  })
  return NextResponse.json({ events })
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  if (!WRITE_ROLES.has(ctx.member.role)) {
    return NextResponse.json({ error: 'Forbidden' }, { status: 403 })
  }

  let body: unknown
  try { body = await req.json() } catch {
    return NextResponse.json({ error: 'Invalid request body' }, { status: 400 })
  }

  const parsed = createSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const event = await prisma.event.create({
    data: {
      communityId: ctx.communityId,
      authorId: ctx.userId,
      title: parsed.data.title,
      description: parsed.data.description,
      startAt: new Date(parsed.data.startAt),
      endAt: parsed.data.endAt ? new Date(parsed.data.endAt) : undefined,
      location: parsed.data.location,
      rsvps: {},
    },
  })

  return NextResponse.json({ event }, { status: 201 })
})
