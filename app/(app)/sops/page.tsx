import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import Link from 'next/link'
import { UpgradePrompt } from '@/components/ui/UpgradePrompt'
import { PLANS } from '@/lib/plans'

export default async function SOPsPage() {
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

  if (!PLANS[member.community.planTier].features.has('sops')) {
    return <UpgradePrompt feature="SOPs" requiredPlan="STANDARD" />
  }

  const canWrite = ['OWNER', 'ADMIN', 'MODERATOR'].includes(member.role)
  const sops = await prisma.sOP.findMany({
    where: { communityId },
    select: { id: true, title: true, version: true, publishedAt: true },
    orderBy: { publishedAt: 'desc' },
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">SOPs</h1>
        {canWrite && (
          <Link href="/sops/new" className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium hover:opacity-90 transition-opacity">
            New SOP
          </Link>
        )}
      </div>
      {sops.length === 0 && <p className="text-sm text-text-muted">No SOPs yet.</p>}
      <div className="flex flex-col gap-1">
        {sops.map(sop => (
          <Link key={sop.id} href={`/sops/${sop.id}`} className="flex items-center justify-between py-2.5 border-b border-border-default last:border-0 hover:text-text-primary transition-colors">
            <span className="text-sm text-text-secondary">{sop.title}</span>
            <div className="flex items-center gap-3">
              <span className="text-[10px] border border-border-default text-text-muted px-1.5 py-0.5 rounded">v{sop.version}</span>
              <span className="text-xs text-text-muted">{new Date(sop.publishedAt).toLocaleDateString()}</span>
            </div>
          </Link>
        ))}
      </div>
    </div>
  )
}
