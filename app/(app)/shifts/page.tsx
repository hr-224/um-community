import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import Link from 'next/link'
import { UpgradePrompt } from '@/components/ui/UpgradePrompt'
import { PLANS } from '@/lib/plans'

export default async function ShiftsPage() {
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

  if (!PLANS[member.community.planTier].features.has('shifts')) {
    return <UpgradePrompt feature="Shifts" requiredPlan="STANDARD" />
  }

  const canWrite = ['OWNER', 'ADMIN', 'MODERATOR'].includes(member.role)
  const shifts = await prisma.shift.findMany({
    where: { communityId },
    orderBy: { startAt: 'asc' },
    take: 50,
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Shifts</h1>
        {canWrite && (
          <Link href="/shifts/new" className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium hover:opacity-90 transition-opacity">
            New Shift
          </Link>
        )}
      </div>
      {shifts.length === 0 && <p className="text-sm text-text-muted">No shifts scheduled.</p>}
      <div className="flex flex-col gap-3">
        {shifts.map(shift => (
          <div key={shift.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-start justify-between gap-3">
              <span className="text-sm font-medium text-text-primary">{shift.title}</span>
              <span className="text-xs text-text-muted flex-shrink-0">
                {new Date(shift.startAt).toLocaleString()}
              </span>
            </div>
            <div className="flex items-center gap-3 mt-1">
              <span className="text-xs text-text-muted">Ends {new Date(shift.endAt).toLocaleString()}</span>
              {shift.slots > 0 && <span className="text-xs text-text-muted">{shift.slots} slots</span>}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
