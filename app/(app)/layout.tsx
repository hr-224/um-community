import { redirect } from 'next/navigation'
import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { AppShell } from '@/components/layout/AppShell'
import { CommunityProvider } from '@/components/providers/CommunityProvider'
import type { CommunityInfo } from '@/types/community'
import type { ReactNode } from 'react'

export default async function AppLayout({ children }: { children: ReactNode }) {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')

  const memberships = await prisma.communityMember.findMany({
    where: { userId: session.user.id, status: 'ACTIVE' },
    include: { community: true },
    orderBy: { joinedAt: 'asc' },
  })

  if (!memberships.length) redirect('/onboarding')

  const cookieStore = await cookies()
  const activeCommunityId = cookieStore.get('active_community_id')?.value
  const activeMembership =
    memberships.find(m => m.communityId === activeCommunityId) ?? memberships[0]

  const communities: CommunityInfo[] = memberships.map(m => ({
    id: m.communityId,
    name: m.community.name,
    slug: m.community.slug,
    logo: m.community.logo,
    planTier: m.community.planTier,
    role: m.role,
  }))

  const activeCommunity: CommunityInfo = {
    id: activeMembership.communityId,
    name: activeMembership.community.name,
    slug: activeMembership.community.slug,
    logo: activeMembership.community.logo,
    planTier: activeMembership.community.planTier,
    role: activeMembership.role,
  }

  return (
    <CommunityProvider initialCommunity={activeCommunity} initialCommunities={communities}>
      <AppShell>{children}</AppShell>
    </CommunityProvider>
  )
}
