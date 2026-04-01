'use client'
import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

interface Message {
  id: string
  senderId: string
  content: string
  createdAt: string
  readAt: string | null
}

export default function MessagesPage() {
  const [messages, setMessages] = useState<Message[]>([])
  const [loading, setLoading] = useState(true)
  const [composing, setComposing] = useState(false)
  const [recipientId, setRecipientId] = useState('')
  const [content, setContent] = useState('')
  const [sending, setSending] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    fetch('/api/messages')
      .then(r => r.json())
      .then(j => { setMessages(j.messages ?? []); setLoading(false) })
      .catch(() => setLoading(false))
  }, [])

  async function sendMessage(e: React.FormEvent) {
    e.preventDefault()
    setSending(true)
    setError('')
    const res = await fetch('/api/messages', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ recipientId, content }),
    })
    const json = await res.json()
    if (!res.ok) { setError(json.error); setSending(false); return }
    setComposing(false)
    setRecipientId('')
    setContent('')
    setSending(false)
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Messages</h1>
        <Button size="sm" onClick={() => setComposing(c => !c)}>
          {composing ? 'Cancel' : 'Compose'}
        </Button>
      </div>

      {composing && (
        <form onSubmit={sendMessage} className="bg-bg-surface border border-border-default rounded-lg p-4 mb-4 flex flex-col gap-3">
          <Input label="Recipient ID" id="recipient" value={recipientId} onChange={e => setRecipientId(e.target.value)} />
          <div>
            <label htmlFor="content" className="block text-xs text-text-muted mb-1.5">Message</label>
            <textarea
              id="content"
              value={content}
              onChange={e => setContent(e.target.value)}
              rows={3}
              className="w-full bg-bg-elevated border border-border-default rounded px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-border-light resize-none"
            />
          </div>
          {error && <p className="text-xs text-danger">{error}</p>}
          <Button type="submit" loading={sending} size="sm">Send</Button>
        </form>
      )}

      {loading && <p className="text-sm text-text-muted">Loading...</p>}
      {!loading && messages.length === 0 && <p className="text-sm text-text-muted">No messages.</p>}

      <div className="flex flex-col gap-2">
        {messages.map(m => (
          <div key={m.id} className={`bg-bg-surface border rounded-lg p-3 ${m.readAt ? 'border-border-default' : 'border-border-light'}`}>
            <div className="flex items-center justify-between mb-1">
              <span className="text-xs text-text-muted">From: {m.senderId}</span>
              <span className="text-xs text-text-muted">{new Date(m.createdAt).toLocaleString()}</span>
            </div>
            <p className="text-sm text-text-secondary">{m.content}</p>
          </div>
        ))}
      </div>
    </div>
  )
}
