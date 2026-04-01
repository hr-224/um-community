import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import { LOAActions } from './LOAActions'

export default async function AdminLOAPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const member = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!member || !['OWNER', 'ADMIN'].includes(member.role)) redirect('/dashboard')

  const loas = await prisma.lOA.findMany({
    where: { communityId, status: 'PENDING' },
    orderBy: { createdAt: 'asc' },
  })

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight mb-6">LOA Requests</h1>
      {loas.length === 0 && <p className="text-sm text-text-muted">No pending LOA requests.</p>}
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
              <LOAActions loaId={loa.id} />
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
