import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import Link from 'next/link'
import { UpgradePrompt } from '@/components/ui/UpgradePrompt'
import { PLANS } from '@/lib/plans'

export default async function DocumentsPage() {
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

  if (!PLANS[member.community.planTier].features.has('documents')) {
    return <UpgradePrompt feature="Documents" requiredPlan="STANDARD" />
  }

  const documents = await prisma.document.findMany({
    where: { communityId },
    orderBy: { createdAt: 'desc' },
    take: 100,
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Documents</h1>
        <Link href="/documents/new" className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium hover:opacity-90 transition-opacity">
          Add Document
        </Link>
      </div>
      {documents.length === 0 && <p className="text-sm text-text-muted">No documents yet.</p>}
      <div className="flex flex-col gap-1">
        {documents.map(doc => (
          <div key={doc.id} className="flex items-center justify-between py-2.5 border-b border-border-default last:border-0">
            <div>
              <p className="text-sm text-text-secondary">{doc.name}</p>
              {doc.category && <p className="text-xs text-text-muted">{doc.category}</p>}
            </div>
            <a href={doc.fileUrl} target="_blank" rel="noopener noreferrer"
              className="text-xs text-text-muted hover:text-text-secondary transition-colors">
              Open ↗
            </a>
          </div>
        ))}
      </div>
    </div>
  )
}
