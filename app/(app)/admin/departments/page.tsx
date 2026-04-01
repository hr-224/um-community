import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function AdminDepartmentsPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const currentMember = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!currentMember || !['OWNER', 'ADMIN'].includes(currentMember.role)) redirect('/dashboard')

  const departments = await prisma.department.findMany({
    where: { communityId },
    include: { ranks: { orderBy: { level: 'asc' } }, _count: { select: { members: true } } },
    orderBy: { sortOrder: 'asc' },
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Departments</h1>
        <button className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium">
          Add Department
        </button>
      </div>

      {departments.length === 0 && <p className="text-sm text-text-muted">No departments yet.</p>}

      <div className="flex flex-col gap-3">
        {departments.map(dept => (
          <div key={dept.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm font-medium">{dept.name}</span>
              <span className="text-xs text-text-muted">{dept._count.members} members</span>
            </div>
            {dept.ranks.length > 0 && (
              <div className="flex flex-wrap gap-1 mt-2">
                {dept.ranks.map(r => (
                  <span key={r.id} className="text-[10px] border border-border-default text-text-muted rounded px-1.5 py-0.5">
                    {r.name}
                  </span>
                ))}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}
