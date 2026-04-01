'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

export default function NewPatrolLogPage() {
  const router = useRouter()
  const [startTime, setStartTime] = useState('')
  const [endTime, setEndTime] = useState('')
  const [notes, setNotes] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError('')
    try {
      const res = await fetch('/api/patrol-logs', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          startTime: new Date(startTime).toISOString(),
          endTime: endTime ? new Date(endTime).toISOString() : undefined,
          notes: notes || undefined,
        }),
      })
      const json = await res.json()
      if (!res.ok) { setError(json.error); return }
      router.push('/patrol-logs')
    } catch { setError('Network error') } finally { setLoading(false) }
  }

  return (
    <div className="max-w-lg">
      <h1 className="text-xl font-semibold tracking-tight mb-6">Log Patrol</h1>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input label="Start Time" id="startTime" type="datetime-local" value={startTime} onChange={e => setStartTime(e.target.value)} />
        <Input label="End Time (optional)" id="endTime" type="datetime-local" value={endTime} onChange={e => setEndTime(e.target.value)} />
        <div>
          <label htmlFor="notes" className="block text-xs text-text-muted mb-1.5">Notes (optional)</label>
          <textarea id="notes" value={notes} onChange={e => setNotes(e.target.value)} rows={3}
            className="w-full bg-bg-elevated border border-border-default rounded px-3 py-2 text-sm text-text-primary placeholder:text-text-faint focus:outline-none focus:border-border-light resize-none"
          />
        </div>
        {error && <p className="text-xs text-red-400">{error}</p>}
        <div className="flex gap-2">
          <Button type="submit" loading={loading}>Save Log</Button>
          <Button type="button" variant="ghost" onClick={() => router.back()}>Cancel</Button>
        </div>
      </form>
    </div>
  )
}
