'use client'
import { Suspense, useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Card } from '@/components/ui/Card'
import { cn } from '@/lib/utils'

type PlanTier = 'FREE' | 'STANDARD' | 'PRO'

const plans = [
  { tier: 'FREE' as PlanTier,     label: 'Free',     price: '$0/mo',   desc: 'Up to 15 members, 1 department' },
  { tier: 'STANDARD' as PlanTier, label: 'Standard', price: '$9/mo',   desc: 'Up to 75 members, 5 departments' },
  { tier: 'PRO' as PlanTier,      label: 'Pro',       price: '$19/mo',  desc: 'Unlimited members + all features' },
]

function OnboardingCreateForm() {
  const router = useRouter()

  const [name, setName] = useState('')
  const [plan, setPlan] = useState<PlanTier>('FREE')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!name.trim()) { setError('Community name is required'); return }
    setLoading(true)
    setError('')

    const res = await fetch('/api/community/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, planTier: plan }),
    })
    const json = await res.json()

    if (!res.ok) { setError(json.error); setLoading(false); return }

    if (json.checkoutUrl) {
      window.location.href = json.checkoutUrl
    } else {
      router.push('/dashboard')
    }
  }

  return (
    <>
      <h1 className="text-xl font-semibold text-center mb-1 tracking-tight">Create your community</h1>
      <p className="text-sm text-text-muted text-center mb-8">Set up your workspace in under a minute</p>

      <form onSubmit={onSubmit} className="flex flex-col gap-5">
        <Input label="Community Name" id="name" value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Los Santos Police Department" />

        <div>
          <p className="text-xs text-text-muted uppercase tracking-wider mb-2">Choose a plan</p>
          <div className="flex flex-col gap-2">
            {plans.map(p => (
              <button
                key={p.tier}
                type="button"
                onClick={() => setPlan(p.tier)}
                className={cn(
                  'flex items-center justify-between p-3.5 rounded border text-left transition-colors',
                  plan === p.tier
                    ? 'border-white bg-bg-elevated'
                    : 'border-border-default hover:border-border-light'
                )}
              >
                <div>
                  <div className="flex items-center gap-2">
                    <span className="text-sm font-medium text-text-primary">{p.label}</span>
                    {p.tier === 'PRO' && (
                      <span className="text-[10px] bg-bg-elevated border border-border-light text-text-muted px-1.5 py-0.5 rounded">Popular</span>
                    )}
                  </div>
                  <p className="text-xs text-text-muted mt-0.5">{p.desc}</p>
                </div>
                <span className="text-sm font-medium text-text-secondary">{p.price}</span>
              </button>
            ))}
          </div>
        </div>

        {error && <p className="text-xs text-danger">{error}</p>}

        <Button type="submit" loading={loading} className="w-full">
          {plan === 'FREE' ? 'Create Community' : 'Continue to Payment'}
        </Button>
      </form>
    </>
  )
}

function JoinForm() {
  const [code, setCode] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [pending, setPending] = useState(false)
  const router = useRouter()

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError('')
    try {
      const res = await fetch('/api/community/join', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code }),
      })
      const json = await res.json()
      if (res.status === 201) { router.push('/dashboard'); return }
      if (res.status === 202) { setPending(true); setLoading(false); return }
      setError(json.error)
      setLoading(false)
    } catch {
      setError('Network error')
      setLoading(false)
    }
  }

  if (pending) {
    return (
      <div className="text-center">
        <p className="text-sm text-text-muted mb-4">Your application has been submitted and is pending review.</p>
        <Button onClick={() => setPending(false)}>Back</Button>
      </div>
    )
  }

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4">
      <Input label="Invite Code" id="code" value={code} onChange={e => setCode(e.target.value)} placeholder="e.g. abc123xyz" />
      {error && <p className="text-xs text-danger">{error}</p>}
      <Button type="submit" loading={loading} className="w-full">Join Community</Button>
    </form>
  )
}

function OnboardingInner() {
  const router = useRouter()
  const params = useSearchParams()
  const success = params.get('success')
  const [tab, setTab] = useState<'create' | 'join'>('create')

  if (success) {
    return (
      <div className="min-h-screen bg-bg-base flex items-center justify-center p-4">
        <Card className="max-w-sm w-full text-center">
          <div className="w-10 h-10 rounded-full bg-success-bg border border-success/30 flex items-center justify-center mx-auto mb-4">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18" className="text-success">
              <path d="M20 6L9 17l-5-5" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </div>
          <h1 className="text-lg font-semibold mb-1">Community created!</h1>
          <p className="text-sm text-text-muted mb-5">Your workspace is ready.</p>
          <Button onClick={() => router.push('/dashboard')} className="w-full">Go to Dashboard</Button>
        </Card>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-bg-base flex items-center justify-center p-4">
      <div className="w-full max-w-lg">
        <div className="flex items-center gap-2.5 justify-center mb-8">
          <div className="w-7 h-7 rounded-lg bg-white" />
          <span className="text-sm font-semibold tracking-tight">CommunityOS</span>
        </div>

        <div className="flex gap-1 mb-6 bg-bg-elevated rounded-lg p-1">
          <button
            onClick={() => setTab('create')}
            className={cn('flex-1 text-sm py-1.5 rounded-md font-medium transition-colors', tab === 'create' ? 'bg-bg-surface text-text-primary' : 'text-text-muted hover:text-text-secondary')}
          >Create</button>
          <button
            onClick={() => setTab('join')}
            className={cn('flex-1 text-sm py-1.5 rounded-md font-medium transition-colors', tab === 'join' ? 'bg-bg-surface text-text-primary' : 'text-text-muted hover:text-text-secondary')}
          >Join</button>
        </div>

        {tab === 'create' ? <OnboardingCreateForm /> : <JoinForm />}
      </div>
    </div>
  )
}

export default function OnboardingPage() {
  return (
    <Suspense>
      <OnboardingInner />
    </Suspense>
  )
}
