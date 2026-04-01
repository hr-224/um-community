import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import { TransferActions } from './TransferActions'

export default async function AdminTransfersPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const member = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!member || !['OWNER', 'ADMIN'].includes(member.role)) redirect('/dashboard')

  const transfers = await prisma.transfer.findMany({
    where: { communityId, status: 'PENDING' },
    orderBy: { createdAt: 'asc' },
  })

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight mb-6">Transfer Requests</h1>
      {transfers.length === 0 && <p className="text-sm text-text-muted">No pending transfers.</p>}
      <div className="flex flex-col gap-3">
        {transfers.map(t => (
          <div key={t.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-xs text-text-muted">{t.fromDeptId} &rarr; {t.toDeptId}</p>
                <p className="text-sm text-text-secondary mt-0.5 line-clamp-2">{t.reason}</p>
              </div>
              <TransferActions transferId={t.id} />
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
