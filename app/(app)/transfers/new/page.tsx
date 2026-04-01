'use client'
import { useState, useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/Button'

export default function NewTransferPage() {
  const router = useRouter()
  const [departments, setDepartments] = useState<{ id: string; name: string }[]>([])
  const [toDeptId, setToDeptId] = useState('')
  const [reason, setReason] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    fetch('/api/admin/departments')
      .then(r => r.json())
      .then(j => setDepartments(j.departments ?? []))
      .catch(() => {})
  }, [])

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError('')
    try {
      const res = await fetch('/api/transfers', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ toDeptId, reason }),
      })
      const json = await res.json()
      if (!res.ok) { setError(json.error); return }
      router.push('/transfers')
    } catch { setError('Network error') } finally { setLoading(false) }
  }

  return (
    <div className="max-w-lg">
      <h1 className="text-xl font-semibold tracking-tight mb-6">Request Transfer</h1>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <div>
          <label htmlFor="dept" className="block text-xs text-text-muted mb-1.5">Target Department</label>
          <select id="dept" value={toDeptId} onChange={e => setToDeptId(e.target.value)}
            className="w-full bg-bg-elevated border border-border-default rounded px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-border-light">
            <option value="">Select department...</option>
            {departments.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}
          </select>
        </div>
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
