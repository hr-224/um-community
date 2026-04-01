'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'

export function TransferActions({ transferId }: { transferId: string }) {
  const router = useRouter()
  const [loading, setLoading] = useState<'approve' | 'deny' | null>(null)
  const [error, setError] = useState('')

  async function review(action: 'approve' | 'deny') {
    setLoading(action)
    setError('')
    try {
      const res = await fetch(`/api/admin/transfers/${transferId}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action }),
      })
      if (!res.ok) { const j = await res.json().catch(() => ({})); setError(j.error ?? 'Failed'); return }
      router.refresh()
    } catch { setError('Network error') } finally { setLoading(null) }
  }

  return (
    <div>
      <div className="flex gap-2">
        <button onClick={() => review('approve')} disabled={loading !== null}
          className="text-xs bg-white text-black px-2.5 py-1 rounded font-medium disabled:opacity-50">
          {loading === 'approve' ? '...' : 'Approve'}
        </button>
        <button onClick={() => review('deny')} disabled={loading !== null}
          className="text-xs border border-border-default text-text-muted px-2.5 py-1 rounded disabled:opacity-50">
          {loading === 'deny' ? '...' : 'Deny'}
        </button>
      </div>
      {error && <p className="text-xs text-red-400 mt-1">{error}</p>}
    </div>
  )
}
