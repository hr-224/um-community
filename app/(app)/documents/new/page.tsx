'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

export default function NewDocumentPage() {
  const router = useRouter()
  const [name, setName] = useState('')
  const [fileUrl, setFileUrl] = useState('')
  const [category, setCategory] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError('')
    try {
      const res = await fetch('/api/documents', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, fileUrl, category: category || undefined }),
      })
      const json = await res.json()
      if (!res.ok) { setError(json.error); return }
      router.push('/documents')
    } catch { setError('Network error') } finally { setLoading(false) }
  }

  return (
    <div className="max-w-lg">
      <h1 className="text-xl font-semibold tracking-tight mb-6">Add Document</h1>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input label="Name" id="name" value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Use of Force Policy.pdf" />
        <Input label="File URL" id="fileUrl" type="url" value={fileUrl} onChange={e => setFileUrl(e.target.value)} placeholder="https://..." />
        <Input label="Category (optional)" id="category" value={category} onChange={e => setCategory(e.target.value)} placeholder="e.g. Policies" />
        {error && <p className="text-xs text-red-400">{error}</p>}
        <div className="flex gap-2">
          <Button type="submit" loading={loading}>Add Document</Button>
          <Button type="button" variant="ghost" onClick={() => router.back()}>Cancel</Button>
        </div>
      </form>
    </div>
  )
}
