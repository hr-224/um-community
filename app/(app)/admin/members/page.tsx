import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function AdminMembersPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const currentMember = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!currentMember || !['OWNER', 'ADMIN'].includes(currentMember.role)) redirect('/dashboard')

  const members = await prisma.communityMember.findMany({
    where: { communityId },
    include: {
      user: { select: { email: true } },
      department: { select: { name: true } },
      rank: { select: { name: true } },
    },
    orderBy: { joinedAt: 'asc' },
  })

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight mb-6">Members</h1>
      <div className="bg-bg-surface border border-border-default rounded-lg overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border-default">
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Member</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Role</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Status</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Department</th>
              <th className="px-4 py-2.5 font-normal"></th>
            </tr>
          </thead>
          <tbody>
            {members.map(m => (
              <tr key={m.id} className="border-b border-border-default last:border-0">
                <td className="px-4 py-2.5 text-text-secondary">{m.user.email}</td>
                <td className="px-4 py-2.5 text-text-muted capitalize">{m.role.toLowerCase()}</td>
                <td className="px-4 py-2.5 text-text-muted capitalize">{m.status.toLowerCase()}</td>
                <td className="px-4 py-2.5 text-text-muted">{m.department?.name ?? '—'}</td>
                <td className="px-4 py-2.5">
                  {m.role !== 'OWNER' && (
                    <span className="text-xs text-text-muted">Edit</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
