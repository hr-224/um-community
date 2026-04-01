'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

export default function NewLOAPage() {
  const router = useRouter()
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [reason, setReason] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError('')
    try {
      const res = await fetch('/api/loa', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ startDate, endDate, reason }),
      })
      const json = await res.json()
      if (!res.ok) { setError(json.error); return }
      router.push('/loa')
    } catch { setError('Network error') } finally { setLoading(false) }
  }

  return (
    <div className="max-w-lg">
      <h1 className="text-xl font-semibold tracking-tight mb-6">Submit LOA Request</h1>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input label="Start Date" id="startDate" type="date" value={startDate} onChange={e => setStartDate(e.target.value)} />
        <Input label="End Date" id="endDate" type="date" value={endDate} onChange={e => setEndDate(e.target.value)} />
        <div>
          <label htmlFor="reason" className="block text-xs text-text-muted mb-1.5">Reason</label>
          <textarea id="reason" value={reason} onChange={e => setReason(e.target.value)} rows={4}
            className="w-full bg-bg-elevated border border-border-default rounded px-3 py-2 text-sm text-text-primary placeholder:text-text-faint focus:outline-none focus:border-border-light resize-none"
          />
        </div>
        {error && <p className="text-xs text-red-400">{error}</p>}
        <div className="flex gap-2">
          <Button type="submit" loading={loading}>Submit</Button>
          <Button type="button" variant="ghost" onClick={() => router.back()}>Cancel</Button>
        </div>
      </form>
    </div>
  )
}
