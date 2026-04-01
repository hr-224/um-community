'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'

export function ApplicationActions({ applicationId }: { applicationId: string }) {
  const router = useRouter()
  const [loading, setLoading] = useState<'approve' | 'deny' | null>(null)

  async function review(action: 'approve' | 'deny') {
    setLoading(action)
    try {
      await fetch(`/api/admin/applications/${applicationId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action }),
      })
      router.refresh()
    } finally {
      setLoading(null)
    }
  }

  return (
    <div className="flex gap-2">
      <button
        onClick={() => review('approve')}
        disabled={loading !== null}
        className="text-xs bg-white text-black px-2.5 py-1 rounded font-medium disabled:opacity-50"
      >
        {loading === 'approve' ? '...' : 'Approve'}
      </button>
      <button
        onClick={() => review('deny')}
        disabled={loading !== null}
        className="text-xs border border-border-default text-text-muted px-2.5 py-1 rounded disabled:opacity-50"
      >
        {loading === 'deny' ? '...' : 'Deny'}
      </button>
    </div>
  )
}
