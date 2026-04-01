import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import { ApplicationActions } from './ApplicationActions'

export default async function AdminApplicationsPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const member = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!member || !['OWNER', 'ADMIN'].includes(member.role)) redirect('/dashboard')

  const applications = await prisma.application.findMany({
    where: { communityId, status: 'PENDING' },
    orderBy: { createdAt: 'asc' },
  })

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight mb-6">Applications</h1>

      {applications.length === 0 && (
        <p className="text-sm text-text-muted">No pending applications.</p>
      )}

      <div className="flex flex-col gap-3">
        {applications.map(app => (
          <div key={app.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm text-text-secondary">{app.applicantUserId}</p>
                <p className="text-xs text-text-muted mt-0.5">
                  Applied {new Date(app.createdAt).toLocaleDateString()}
                </p>
              </div>
              <ApplicationActions applicationId={app.id} />
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
