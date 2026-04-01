import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const sendSchema = z.object({
  recipientId: z.string().min(1, 'recipientId required'),
  content: z.string().min(1, 'Content required').max(2000),
})

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const messages = await prisma.message.findMany({
    where: { communityId: ctx.communityId, recipientId: ctx.userId },
    orderBy: { createdAt: 'desc' },
    take: 50,
  })
  return NextResponse.json({ messages })
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  let body: unknown
  try { body = await req.json() } catch {
    return NextResponse.json({ error: 'Invalid request body' }, { status: 400 })
  }

  const parsed = sendSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  if (parsed.data.recipientId === ctx.userId) {
    return NextResponse.json({ error: 'Cannot message yourself' }, { status: 400 })
  }

  const recipientMember = await prisma.communityMember.findFirst({
    where: { userId: parsed.data.recipientId, communityId: ctx.communityId, status: 'ACTIVE' },
  })
  if (!recipientMember) {
    return NextResponse.json({ error: 'Recipient not found in this community' }, { status: 400 })
  }

  const message = await prisma.message.create({
    data: {
      communityId: ctx.communityId,
      senderId: ctx.userId,
      recipientId: parsed.data.recipientId,
      content: parsed.data.content,
    },
  })

  return NextResponse.json({ message }, { status: 201 })
})
