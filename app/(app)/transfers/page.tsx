import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import Link from 'next/link'
import { UpgradePrompt } from '@/components/ui/UpgradePrompt'
import { PLANS } from '@/lib/plans'
import { cn } from '@/lib/utils'

export default async function TransfersPage() {
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

  if (!PLANS[member.community.planTier].features.has('transfers')) {
    return <UpgradePrompt feature="Transfers" requiredPlan="STANDARD" />
  }

  const transfers = await prisma.transfer.findMany({
    where: { communityId, memberId: member.id },
    orderBy: { createdAt: 'desc' },
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Transfers</h1>
        <Link href="/transfers/new" className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium hover:opacity-90 transition-opacity">
          Request Transfer
        </Link>
      </div>
      {transfers.length === 0 && <p className="text-sm text-text-muted">No transfer requests.</p>}
      <div className="flex flex-col gap-3">
        {transfers.map(t => (
          <div key={t.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-center justify-between">
              <p className="text-sm text-text-secondary line-clamp-1">{t.reason}</p>
              <span className={cn(
                'text-[10px] border px-1.5 py-0.5 rounded capitalize',
                t.status === 'APPROVED' ? 'border-green-500/30 text-green-400' :
                t.status === 'DENIED' ? 'border-red-500/30 text-red-400' :
                'border-border-default text-text-muted'
              )}>{t.status.toLowerCase()}</span>
            </div>
            <p className="text-xs text-text-muted mt-1">{new Date(t.createdAt).toLocaleDateString()}</p>
          </div>
        ))}
      </div>
    </div>
  )
}
