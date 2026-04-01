import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'
import { cn } from '@/lib/utils'
import type { PlanTier } from '@/lib/generated/prisma/client'

const planDetails = [
  {
    tier: 'FREE' as PlanTier,
    label: 'Free',
    price: '$0/mo',
    desc: 'Up to 15 members, 1 department',
    features: ['Dashboard', 'Roster', 'Events', 'Announcements', 'Messages', 'Applications'],
  },
  {
    tier: 'STANDARD' as PlanTier,
    label: 'Standard',
    price: '$9/mo',
    desc: 'Up to 75 members, 5 departments',
    features: ['Everything in Free', 'Patrol Logs', 'Shifts', 'SOPs', 'Documents', 'LOA', 'Transfers', 'Chain of Command', 'Discord Integration'],
  },
  {
    tier: 'PRO' as PlanTier,
    label: 'Pro',
    price: '$19/mo',
    desc: 'Unlimited members + all features',
    features: ['Everything in Standard', 'Quizzes', 'Analytics', 'Custom Fields', 'Audit Log', 'API Keys'],
  },
]

export default async function BillingPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const community = await prisma.community.findUnique({
    where: { id: communityId },
    select: { planTier: true },
  })
  if (!community) redirect('/onboarding')

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight mb-2">Billing &amp; Plans</h1>
      <p className="text-sm text-text-muted mb-8">
        Current plan: <span className="text-text-secondary capitalize">{community.planTier.toLowerCase()}</span>
      </p>

      <div className="grid grid-cols-3 gap-4">
        {planDetails.map((plan) => {
          const isCurrent = community.planTier === plan.tier
          return (
            <div
              key={plan.tier}
              className={cn(
                'bg-bg-surface border rounded-lg p-5',
                isCurrent ? 'border-white' : 'border-border-default'
              )}
            >
              <div className="flex items-center justify-between mb-1">
                <span className="text-sm font-semibold">{plan.label}</span>
                {isCurrent && (
                  <span className="text-[10px] border border-border-light text-text-muted px-1.5 py-0.5 rounded">Current</span>
                )}
              </div>
              <p className="text-xl font-bold mb-1">{plan.price}</p>
              <p className="text-xs text-text-muted mb-4">{plan.desc}</p>
              <ul className="flex flex-col gap-1 mb-5">
                {plan.features.map(f => (
                  <li key={f} className="text-xs text-text-muted flex items-center gap-1.5">
                    <span className="text-text-secondary">✓</span> {f}
                  </li>
                ))}
              </ul>
              {!isCurrent && plan.tier !== 'FREE' && (
                <a
                  href={`/api/community/create?upgrade=${plan.tier}`}
                  className="block text-center text-xs bg-white text-black px-3 py-2 rounded font-medium hover:opacity-90 transition-opacity"
                >
                  Upgrade to {plan.label}
                </a>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
