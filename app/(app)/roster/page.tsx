import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function RosterPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const members = await prisma.communityMember.findMany({
    where: { communityId, status: 'ACTIVE' },
    include: {
      user: { select: { email: true, avatar: true } },
      department: { select: { name: true } },
      rank: { select: { name: true } },
    },
    orderBy: [{ role: 'asc' }, { joinedAt: 'asc' }],
    take: 100,
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Roster</h1>
        <span className="text-xs text-text-muted">{members.length} members</span>
      </div>
      <div className="bg-bg-surface border border-border-default rounded-lg overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border-default">
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Member</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Department</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Rank</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Role</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Callsign</th>
            </tr>
          </thead>
          <tbody>
            {members.map(m => (
              <tr key={m.id} className="border-b border-border-default last:border-0 hover:bg-bg-elevated transition-colors">
                <td className="px-4 py-2.5">
                  <div className="flex items-center gap-2">
                    <div className="w-6 h-6 rounded-full bg-bg-elevated flex items-center justify-center text-[10px] text-text-muted flex-shrink-0">
                      {m.user.email[0]?.toUpperCase()}
                    </div>
                    <span className="text-text-secondary truncate max-w-[160px]">{m.user.email}</span>
                  </div>
                </td>
                <td className="px-4 py-2.5 text-text-muted">{m.department?.name ?? '—'}</td>
                <td className="px-4 py-2.5 text-text-muted">{m.rank?.name ?? '—'}</td>
                <td className="px-4 py-2.5 text-text-muted capitalize">{m.role.toLowerCase()}</td>
                <td className="px-4 py-2.5 text-text-muted">{m.callsign ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
