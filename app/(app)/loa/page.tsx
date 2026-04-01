import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import Link from 'next/link'
import { UpgradePrompt } from '@/components/ui/UpgradePrompt'
import { PLANS } from '@/lib/plans'
import { cn } from '@/lib/utils'

export default async function LOAPage() {
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

  if (!PLANS[member.community.planTier].features.has('loa')) {
    return <UpgradePrompt feature="Leave of Absence" requiredPlan="STANDARD" />
  }

  const loas = await prisma.lOA.findMany({
    where: { communityId, memberId: member.id },
    orderBy: { createdAt: 'desc' },
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Leave of Absence</h1>
        <Link href="/loa/new" className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium hover:opacity-90 transition-opacity">
          Submit LOA
        </Link>
      </div>
      {loas.length === 0 && <p className="text-sm text-text-muted">No LOA requests.</p>}
      <div className="flex flex-col gap-3">
        {loas.map(loa => (
          <div key={loa.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm text-text-secondary">
                  {new Date(loa.startDate).toLocaleDateString()} – {new Date(loa.endDate).toLocaleDateString()}
                </p>
                <p className="text-xs text-text-muted mt-1 line-clamp-2">{loa.reason}</p>
              </div>
              <span className={cn(
                'text-[10px] border px-1.5 py-0.5 rounded capitalize',
                loa.status === 'APPROVED' ? 'border-green-500/30 text-green-400' :
                loa.status === 'DENIED' ? 'border-red-500/30 text-red-400' :
                'border-border-default text-text-muted'
              )}>
                {loa.status.toLowerCase()}
              </span>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
