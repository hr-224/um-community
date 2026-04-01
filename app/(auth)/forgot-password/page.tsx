'use client'
import { useState } from 'react'
import Link from 'next/link'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Card } from '@/components/ui/Card'

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [loading, setLoading] = useState(false)
  const [sent, setSent] = useState(false)

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    await fetch('/api/auth/forgot-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email }),
    })
    setSent(true)
    setLoading(false)
  }

  if (sent) return (
    <Card>
      <h1 className="text-lg font-semibold mb-1">Check your email</h1>
      <p className="text-sm text-text-muted">If an account exists, a reset link was sent.</p>
      <Link href="/login" className="text-xs text-text-muted hover:text-text-secondary mt-4 inline-block">Back to login</Link>
    </Card>
  )

  return (
    <Card>
      <h1 className="text-lg font-semibold mb-1">Reset password</h1>
      <p className="text-sm text-text-muted mb-6">Enter your email to receive a reset link</p>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input label="Email" id="email" type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="you@example.com" />
        <Button type="submit" loading={loading} className="w-full">Send reset link</Button>
      </form>
      <Link href="/login" className="text-xs text-text-muted hover:text-text-secondary mt-4 inline-block">Back to login</Link>
    </Card>
  )
}
