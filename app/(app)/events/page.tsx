import Link from 'next/link'
import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function EventsPage() {
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
  const events = await prisma.event.findMany({
    where: { communityId },
    orderBy: { startAt: 'asc' },
    take: 50,
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Events</h1>
        {canWrite && (
          <Link href="/events/new" className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium hover:opacity-90 transition-opacity">
            New Event
          </Link>
        )}
      </div>
      {events.length === 0 && <p className="text-sm text-text-muted">No events scheduled.</p>}
      <div className="flex flex-col gap-3">
        {events.map(ev => (
          <div key={ev.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-start justify-between gap-3">
              <span className="text-sm font-medium text-text-primary">{ev.title}</span>
              <span className="text-xs text-text-muted flex-shrink-0">
                {new Date(ev.startAt).toLocaleString()}
              </span>
            </div>
            {ev.description && <p className="text-sm text-text-muted mt-1.5 line-clamp-2">{ev.description}</p>}
            {ev.location && <p className="text-xs text-text-muted mt-1">{ev.location}</p>}
          </div>
        ))}
      </div>
    </div>
  )
}
