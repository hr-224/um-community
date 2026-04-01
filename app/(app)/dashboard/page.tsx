import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import { cn } from '@/lib/utils'

export default async function DashboardPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')

  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const [memberCount, pendingApplications, departments, recentMembers] = await Promise.all([
    prisma.communityMember.count({ where: { communityId, status: 'ACTIVE' } }),
    prisma.application.count({ where: { communityId, status: 'PENDING' } }),
    prisma.department.findMany({
      where: { communityId },
      include: { _count: { select: { members: true } } },
      orderBy: { sortOrder: 'asc' },
    }),
    prisma.communityMember.findMany({
      where: { communityId, status: 'ACTIVE' },
      include: {
        user: { select: { email: true, avatar: true } },
        department: { select: { name: true } },
        rank: { select: { name: true } },
      },
      orderBy: { joinedAt: 'desc' },
      take: 5,
    }),
  ])

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight mb-6">Dashboard</h1>

      <div className="grid grid-cols-2 gap-4 mb-8">
        <div className="bg-bg-surface border border-border-default border-t-2 border-t-white rounded-lg p-4">
          <p className="text-[11px] uppercase tracking-[0.8px] text-text-muted mb-1">Members</p>
          <p className="text-2xl font-semibold">{memberCount}</p>
        </div>
        <div className={cn(
          'bg-bg-surface border border-border-default rounded-lg p-4',
          pendingApplications > 0 ? 'border-t-2 border-t-white' : ''
        )}>
          <p className="text-[11px] uppercase tracking-[0.8px] text-text-muted mb-1">Pending Applications</p>
          <p className="text-2xl font-semibold">{pendingApplications}</p>
        </div>
      </div>

      {departments.length > 0 && (
        <div className="mb-8">
          <p className="text-[11px] uppercase tracking-[0.8px] text-text-muted mb-3">Departments</p>
          <div className="flex flex-col gap-1">
            {departments.map(dept => (
              <div key={dept.id} className="flex items-center justify-between py-2 border-b border-border-default last:border-0">
                <span className="text-sm text-text-secondary">{dept.name}</span>
                <span className="text-xs text-text-muted">{dept._count.members} members</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {recentMembers.length > 0 && (
        <div>
          <p className="text-[11px] uppercase tracking-[0.8px] text-text-muted mb-3">Recent Members</p>
          <div className="flex flex-col gap-1">
            {recentMembers.map(m => (
              <div key={m.id} className="flex items-center gap-3 py-2 border-b border-border-default last:border-0">
                <div className="w-7 h-7 rounded-full bg-bg-elevated flex items-center justify-center text-xs text-text-muted flex-shrink-0">
                  {m.user.email[0]?.toUpperCase()}
                </div>
                <div className="min-w-0">
                  <p className="text-sm text-text-secondary truncate">{m.user.email}</p>
                  {m.department && <p className="text-xs text-text-muted">{m.department.name}{m.rank ? ` · ${m.rank.name}` : ''}</p>}
                </div>
                <span className="ml-auto text-[10px] text-text-muted capitalize">{m.role.toLowerCase()}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
