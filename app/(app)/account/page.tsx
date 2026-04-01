'use client'
import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

interface UserProfile {
  id: string
  email: string
  avatar: string | null
  discordUsername: string | null
  totpEnabled: boolean
}

export default function AccountPage() {
  const [user, setUser] = useState<UserProfile | null>(null)
  const [avatar, setAvatar] = useState('')
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)

  useEffect(() => {
    fetch('/api/account')
      .then(r => r.json())
      .then(j => {
        setUser(j.user)
        setAvatar(j.user?.avatar ?? '')
      })
  }, [])

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setSaving(true)
    setError('')
    setSuccess(false)
    try {
      const res = await fetch('/api/account', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ avatar: avatar || null }),
      })
      const json = await res.json()
      if (!res.ok) { setError(json.error); return }
      setUser(json.user)
      setSuccess(true)
    } catch { setError('Network error') } finally { setSaving(false) }
  }

  if (!user) return <div className="text-sm text-text-muted">Loading...</div>

  return (
    <div className="max-w-lg">
      <h1 className="text-xl font-semibold tracking-tight mb-6">Account Settings</h1>

      <div className="mb-6 pb-6 border-b border-border-default">
        <p className="text-xs text-text-muted mb-0.5">Email</p>
        <p className="text-sm text-text-secondary">{user.email}</p>
        {user.discordUsername && (
          <>
            <p className="text-xs text-text-muted mt-3 mb-0.5">Discord</p>
            <p className="text-sm text-text-secondary">{user.discordUsername}</p>
          </>
        )}
        <p className="text-xs text-text-muted mt-3 mb-0.5">Two-Factor Auth</p>
        <p className="text-sm text-text-secondary">{user.totpEnabled ? 'Enabled' : 'Disabled'}</p>
      </div>

      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input
          label="Avatar URL (optional)"
          id="avatar"
          type="url"
          value={avatar}
          onChange={e => setAvatar(e.target.value)}
          placeholder="https://..."
        />
        {error && <p className="text-xs text-red-400">{error}</p>}
        {success && <p className="text-xs text-text-muted">Saved.</p>}
        <Button type="submit" loading={saving}>Save Changes</Button>
      </form>
    </div>
  )
}
