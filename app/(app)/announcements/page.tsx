import Link from 'next/link'
import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function AnnouncementsPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const member = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!member) redirect('/onboarding')

  const canWrite = ['OWNER', 'ADMIN', 'MODERATOR'].includes(member.role)

  const announcements = await prisma.announcement.findMany({
    where: { communityId },
    orderBy: [{ isPinned: 'desc' }, { publishedAt: 'desc' }],
    take: 50,
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Announcements</h1>
        {canWrite && (
          <Link href="/announcements/new" className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium hover:opacity-90 transition-opacity">
            New Announcement
          </Link>
        )}
      </div>

      {announcements.length === 0 && (
        <p className="text-sm text-text-muted">No announcements yet.</p>
      )}

      <div className="flex flex-col gap-3">
        {announcements.map(a => (
          <div key={a.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                {a.isPinned && (
                  <span className="text-[10px] uppercase tracking-wider text-text-muted border border-border-default rounded px-1.5 py-0.5 mr-2">Pinned</span>
                )}
                <span className="text-sm font-medium text-text-primary">{a.title}</span>
              </div>
              <span className="text-xs text-text-muted flex-shrink-0">
                {new Date(a.publishedAt).toLocaleDateString()}
              </span>
            </div>
            <p className="text-sm text-text-muted mt-2 line-clamp-3">{a.content}</p>
          </div>
        ))}
      </div>
    </div>
  )
}
