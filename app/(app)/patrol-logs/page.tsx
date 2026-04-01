import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import Link from 'next/link'
import { UpgradePrompt } from '@/components/ui/UpgradePrompt'
import { PLANS } from '@/lib/plans'

export default async function PatrolLogsPage() {
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

  if (!PLANS[member.community.planTier].features.has('patrolLogs')) {
    return <UpgradePrompt feature="Patrol Logs" requiredPlan="STANDARD" />
  }

  const patrolLogs = await prisma.patrolLog.findMany({
    where: { communityId },
    orderBy: { startTime: 'desc' },
    take: 50,
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Patrol Logs</h1>
        <Link href="/patrol-logs/new" className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium hover:opacity-90 transition-opacity">
          Log Patrol
        </Link>
      </div>

      {patrolLogs.length === 0 && <p className="text-sm text-text-muted">No patrol logs yet.</p>}

      <div className="flex flex-col gap-2">
        {patrolLogs.map(log => (
          <div key={log.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-center justify-between">
              <span className="text-sm text-text-secondary">
                {new Date(log.startTime).toLocaleString()}
                {log.endTime && ` → ${new Date(log.endTime).toLocaleString()}`}
              </span>
              {log.endTime && (
                <span className="text-xs text-text-muted">
                  {Math.round((new Date(log.endTime).getTime() - new Date(log.startTime).getTime()) / 60000)}m
                </span>
              )}
            </div>
            {log.notes && <p className="text-xs text-text-muted mt-1 line-clamp-2">{log.notes}</p>}
          </div>
        ))}
      </div>
    </div>
  )
}
