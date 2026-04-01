'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

export default function NewEventPage() {
  const router = useRouter()
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [startAt, setStartAt] = useState('')
  const [endAt, setEndAt] = useState('')
  const [location, setLocation] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError('')
    const res = await fetch('/api/events', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, description, startAt, endAt: endAt || undefined, location: location || undefined }),
    })
    const json = await res.json()
    if (!res.ok) { setError(json.error); setLoading(false); return }
    router.push('/events')
  }

  return (
    <div className="max-w-2xl">
      <h1 className="text-xl font-semibold tracking-tight mb-6">New Event</h1>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input label="Title" id="title" value={title} onChange={e => setTitle(e.target.value)} />
        <Input label="Start" id="startAt" type="datetime-local" value={startAt} onChange={e => setStartAt(e.target.value)} />
        <Input label="End (optional)" id="endAt" type="datetime-local" value={endAt} onChange={e => setEndAt(e.target.value)} />
        <Input label="Location (optional)" id="location" value={location} onChange={e => setLocation(e.target.value)} />
        <div>
          <label htmlFor="description" className="block text-xs text-text-muted mb-1.5">Description (optional)</label>
          <textarea
            id="description"
            value={description}
            onChange={e => setDescription(e.target.value)}
            rows={4}
            className="w-full bg-bg-elevated border border-border-default rounded px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-border-light resize-none"
          />
        </div>
        {error && <p className="text-xs text-danger">{error}</p>}
        <div className="flex gap-2">
          <Button type="submit" loading={loading}>Create Event</Button>
          <Button type="button" variant="ghost" onClick={() => router.back()}>Cancel</Button>
        </div>
      </form>
    </div>
  )
}
