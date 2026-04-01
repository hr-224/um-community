import Link from 'next/link'

interface UpgradePromptProps {
  feature: string
  requiredPlan: 'STANDARD' | 'PRO'
}

export function UpgradePrompt({ feature, requiredPlan }: UpgradePromptProps) {
  const planLabel = requiredPlan === 'STANDARD' ? 'Standard ($9/mo)' : 'Pro ($19/mo)'
  return (
    <div className="flex flex-col items-center justify-center py-20 text-center">
      <div className="w-10 h-10 rounded-full bg-bg-elevated border border-border-default flex items-center justify-center mb-4">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" className="text-text-muted">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m0 0v2m0-2h2m-2 0H10m2-10a4 4 0 110 8 4 4 0 010-8z" />
        </svg>
      </div>
      <h2 className="text-base font-semibold mb-1">{feature}</h2>
      <p className="text-sm text-text-muted mb-5">
        Upgrade to <span className="text-text-secondary">{planLabel}</span> to unlock this feature.
      </p>
      <Link
        href="/billing"
        className="text-xs bg-white text-black px-4 py-2 rounded font-medium hover:opacity-90 transition-opacity"
      >
        View Plans
      </Link>
    </div>
  )
}
