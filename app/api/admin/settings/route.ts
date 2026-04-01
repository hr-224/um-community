import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const updateSchema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters').max(60).optional(),
  isPublic: z.boolean().optional(),
  autoApproveMembers: z.boolean().optional(),
  discordServerId: z.string().nullable().optional(),
})

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const community = await prisma.community.findUnique({
    where: { id: ctx.communityId },
    select: { id: true, name: true, slug: true, logo: true, isPublic: true, autoApproveMembers: true, discordServerId: true },
  })
  return NextResponse.json({ settings: community })
}, { adminOnly: true })

export const PATCH = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const body = await req.json()
  const parsed = updateSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const community = await prisma.community.update({
    where: { id: ctx.communityId },
    data: parsed.data,
  })

  return NextResponse.json({ community })
}, { adminOnly: true })
