import { NextResponse } from 'next/server'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'

export async function POST(req: Request) {
  const session = await auth()
  if (!session?.user?.id) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })

  let body: { code?: string }
  try { body = await req.json() } catch { return NextResponse.json({ error: 'Invalid body' }, { status: 400 }) }

  const code: string | undefined = body?.code
  if (!code) return NextResponse.json({ error: 'code required' }, { status: 400 })

  const invite = await prisma.inviteLink.findFirst({
    where: {
      code,
      isActive: true,
      OR: [{ expiresAt: null }, { expiresAt: { gt: new Date() } }],
    },
  })
  if (!invite) return NextResponse.json({ error: 'Invalid or expired invite' }, { status: 404 })

  if (invite.maxUses !== null && invite.useCount >= invite.maxUses) {
    return NextResponse.json({ error: 'Invite link has reached its usage limit' }, { status: 410 })
  }

  const existing = await prisma.communityMember.findFirst({
    where: { communityId: invite.communityId, userId: session.user.id },
  })
  if (existing) return NextResponse.json({ error: 'Already a member' }, { status: 409 })

  if (invite.type === 'DIRECT_ADMIT') {
    const member = await prisma.communityMember.create({
      data: { communityId: invite.communityId, userId: session.user.id, status: 'ACTIVE' },
    })
    await prisma.inviteLink.update({
      where: { id: invite.id },
      data: { useCount: { increment: 1 } },
    })
    return NextResponse.json({ member }, { status: 201 })
  }

  // STANDARD invite: check community settings
  const community = await prisma.community.findUnique({
    where: { id: invite.communityId },
    select: { isPublic: true, autoApproveMembers: true },
  })

  if (community?.isPublic && community.autoApproveMembers) {
    const member = await prisma.communityMember.create({
      data: { communityId: invite.communityId, userId: session.user.id, status: 'ACTIVE' },
    })
    await prisma.inviteLink.update({
      where: { id: invite.id },
      data: { useCount: { increment: 1 } },
    })
    return NextResponse.json({ member }, { status: 201 })
  }

  // Private community: create pending application
  const application = await prisma.application.create({
    data: {
      communityId: invite.communityId,
      applicantUserId: session.user.id,
      status: 'PENDING',
      formData: {},
    },
  })
  await prisma.inviteLink.update({
    where: { id: invite.id },
    data: { useCount: { increment: 1 } },
  })
  return NextResponse.json({ application, status: 'pending' }, { status: 202 })
}
