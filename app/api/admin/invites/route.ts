import { NextResponse } from 'next/server'
import { z } from 'zod'
import { nanoid } from 'nanoid'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const createSchema = z.object({
  type: z.enum(['STANDARD', 'DIRECT_ADMIT']).default('STANDARD'),
  maxUses: z.number().int().positive().optional(),
  expiresAt: z.string().datetime().optional(),
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const inviteLink = await prisma.inviteLink.create({
    data: {
      communityId: ctx.communityId,
      createdBy: ctx.userId,
      code: nanoid(10),
      type: parsed.data.type,
      maxUses: parsed.data.maxUses,
      expiresAt: parsed.data.expiresAt ? new Date(parsed.data.expiresAt) : undefined,
    },
  })

  return NextResponse.json({ inviteLink }, { status: 201 })
}, { adminOnly: true })
