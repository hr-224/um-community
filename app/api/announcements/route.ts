import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const WRITE_ROLES = new Set(['OWNER', 'ADMIN', 'MODERATOR'])

const createSchema = z.object({
  title: z.string().min(1, 'Title required').max(200),
  content: z.string().min(1, 'Content required'),
  isPinned: z.boolean().optional().default(false),
})

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const announcements = await prisma.announcement.findMany({
    where: { communityId: ctx.communityId },
    orderBy: [{ isPinned: 'desc' }, { publishedAt: 'desc' }],
    take: 50,
  })
  return NextResponse.json({ announcements })
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

  const announcement = await prisma.announcement.create({
    data: {
      communityId: ctx.communityId,
      authorId: ctx.userId,
      title: parsed.data.title,
      content: parsed.data.content,
      isPinned: parsed.data.isPinned,
    },
  })

  return NextResponse.json({ announcement }, { status: 201 })
})
