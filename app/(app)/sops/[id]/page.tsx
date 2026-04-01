import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect, notFound } from 'next/navigation'
import { PLANS } from '@/lib/plans'
import { UpgradePrompt } from '@/components/ui/UpgradePrompt'

export default async function SOPPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const member = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
    include: { community: { select: { planTier: true } } },
  })
  if (!member) redirect('/onboarding')

  if (!PLANS[member.community.planTier].features.has('sops')) {
    return <UpgradePrompt feature="SOPs" requiredPlan="STANDARD" />
  }

  const sop = await prisma.sOP.findFirst({ where: { id, communityId } })
  if (!sop) notFound()

  return (
    <div className="max-w-3xl">
      <div className="flex items-start justify-between mb-6">
        <div>
          <h1 className="text-xl font-semibold tracking-tight">{sop.title}</h1>
          <p className="text-xs text-text-muted mt-1">v{sop.version} · {new Date(sop.publishedAt).toLocaleDateString()}</p>
        </div>
      </div>
      <div className="bg-bg-surface border border-border-default rounded-lg p-6">
        <pre className="text-sm text-text-secondary whitespace-pre-wrap font-sans leading-relaxed">{sop.content}</pre>
      </div>
    </div>
  )
}
