import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import { UpgradePrompt } from '@/components/ui/UpgradePrompt'
import { PLANS } from '@/lib/plans'

export default async function ChainOfCommandPage() {
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

  if (!PLANS[member.community.planTier].features.has('chainOfCommand')) {
    return <UpgradePrompt feature="Chain of Command" requiredPlan="STANDARD" />
  }

  const departments = await prisma.department.findMany({
    where: { communityId },
    include: {
      ranks: { orderBy: { level: 'desc' } },
      members: {
        where: { status: 'ACTIVE' },
        include: {
          user: { select: { email: true, avatar: true } },
          rank: { select: { name: true, level: true, isCommand: true } },
        },
        orderBy: { joinedAt: 'asc' },
      },
    },
    orderBy: { sortOrder: 'asc' },
  })

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight mb-6">Chain of Command</h1>

      {departments.length === 0 && <p className="text-sm text-text-muted">No departments configured yet.</p>}

      <div className="flex flex-col gap-8">
        {departments.map(dept => {
          const commandStaff = dept.members.filter(m => m.rank?.isCommand)
          const regularStaff = dept.members.filter(m => !m.rank?.isCommand)
          return (
            <div key={dept.id}>
              <h2 className="text-sm font-semibold mb-3 flex items-center gap-2">
                {dept.name}
                <span className="text-[10px] text-text-muted font-normal">{dept.members.length} members</span>
              </h2>

              {commandStaff.length > 0 && (
                <div className="mb-3">
                  <p className="text-[10px] uppercase tracking-wider text-text-faint mb-2">Command Staff</p>
                  <div className="flex flex-wrap gap-2">
                    {commandStaff.map(m => (
                      <div key={m.id} className="bg-bg-elevated border border-white/10 rounded-lg px-3 py-2 min-w-[140px]">
                        <p className="text-xs text-text-primary truncate">{m.user.email.split('@')[0]}</p>
                        <p className="text-[10px] text-text-muted">{m.rank?.name}</p>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {regularStaff.length > 0 && (
                <div className="flex flex-col gap-1">
                  {regularStaff.map(m => (
                    <div key={m.id} className="flex items-center gap-3 py-1.5 border-b border-border-default last:border-0">
                      <div className="w-6 h-6 rounded-full bg-bg-elevated flex items-center justify-center text-[10px] text-text-muted flex-shrink-0">
                        {m.user.email[0]?.toUpperCase()}
                      </div>
                      <span className="text-sm text-text-secondary truncate">{m.user.email.split('@')[0]}</span>
                      {m.rank && <span className="ml-auto text-xs text-text-muted">{m.rank.name}</span>}
                    </div>
                  ))}
                </div>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
