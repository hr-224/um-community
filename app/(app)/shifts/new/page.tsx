'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

export default function NewShiftPage() {
  const router = useRouter()
  const [title, setTitle] = useState('')
  const [startAt, setStartAt] = useState('')
  const [endAt, setEndAt] = useState('')
  const [slots, setSlots] = useState('0')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError('')
    try {
      const res = await fetch('/api/shifts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          title,
          startAt: new Date(startAt).toISOString(),
          endAt: new Date(endAt).toISOString(),
          slots: parseInt(slots) || 0,
        }),
      })
      const json = await res.json()
      if (!res.ok) { setError(json.error); return }
      router.push('/shifts')
    } catch { setError('Network error') } finally { setLoading(false) }
  }

  return (
    <div className="max-w-lg">
      <h1 className="text-xl font-semibold tracking-tight mb-6">New Shift</h1>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input label="Title" id="title" value={title} onChange={e => setTitle(e.target.value)} />
        <Input label="Start" id="startAt" type="datetime-local" value={startAt} onChange={e => setStartAt(e.target.value)} />
        <Input label="End" id="endAt" type="datetime-local" value={endAt} onChange={e => setEndAt(e.target.value)} />
        <Input label="Slots (0 = unlimited)" id="slots" type="number" value={slots} onChange={e => setSlots(e.target.value)} />
        {error && <p className="text-xs text-red-400">{error}</p>}
        <div className="flex gap-2">
          <Button type="submit" loading={loading}>Create Shift</Button>
          <Button type="button" variant="ghost" onClick={() => router.back()}>Cancel</Button>
        </div>
      </form>
    </div>
  )
}
