'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

export default function NewSOPPage() {
  const router = useRouter()
  const [title, setTitle] = useState('')
  const [content, setContent] = useState('')
  const [version, setVersion] = useState('1.0')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError('')
    try {
      const res = await fetch('/api/sops', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ title, content, version }),
      })
      const json = await res.json()
      if (!res.ok) { setError(json.error); return }
      router.push('/sops')
    } catch { setError('Network error') } finally { setLoading(false) }
  }

  return (
    <div className="max-w-2xl">
      <h1 className="text-xl font-semibold tracking-tight mb-6">New SOP</h1>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input label="Title" id="title" value={title} onChange={e => setTitle(e.target.value)} />
        <Input label="Version" id="version" value={version} onChange={e => setVersion(e.target.value)} />
        <div>
          <label htmlFor="content" className="block text-xs text-text-muted mb-1.5">Content</label>
          <textarea id="content" value={content} onChange={e => setContent(e.target.value)} rows={12}
            className="w-full bg-bg-elevated border border-border-default rounded px-3 py-2 text-sm text-text-primary placeholder:text-text-faint focus:outline-none focus:border-border-light resize-none font-mono"
          />
        </div>
        {error && <p className="text-xs text-red-400">{error}</p>}
        <div className="flex gap-2">
          <Button type="submit" loading={loading}>Publish SOP</Button>
          <Button type="button" variant="ghost" onClick={() => router.back()}>Cancel</Button>
        </div>
      </form>
    </div>
  )
}
