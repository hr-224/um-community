'use client'
import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

interface Settings {
  name: string
  isPublic: boolean
  autoApproveMembers: boolean
  discordServerId: string | null
}

export default function AdminSettingsPage() {
  const [settings, setSettings] = useState<Settings | null>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)

  useEffect(() => {
    fetch('/api/admin/settings')
      .then(r => r.json())
      .then(j => setSettings(j.settings))
  }, [])

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!settings) return
    setSaving(true)
    setError('')
    setSuccess(false)
    try {
      const res = await fetch('/api/admin/settings', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(settings),
      })
      const json = await res.json()
      if (!res.ok) { setError(json.error); return }
      setSuccess(true)
    } catch {
      setError('Network error')
    } finally {
      setSaving(false)
    }
  }

  if (!settings) return <div className="text-sm text-text-muted">Loading...</div>

  return (
    <div className="max-w-lg">
      <h1 className="text-xl font-semibold tracking-tight mb-6">Community Settings</h1>
      <form onSubmit={onSubmit} className="flex flex-col gap-5">
        <Input
          label="Community Name"
          id="name"
          value={settings.name}
          onChange={e => setSettings(s => s && { ...s, name: e.target.value })}
        />
        <label className="flex items-center gap-2 text-sm text-text-muted cursor-pointer">
          <input
            type="checkbox"
            checked={settings.isPublic}
            onChange={e => setSettings(s => s && { ...s, isPublic: e.target.checked })}
            className="accent-white"
          />
          Public community (members can join via invite link)
        </label>
        <label className="flex items-center gap-2 text-sm text-text-muted cursor-pointer">
          <input
            type="checkbox"
            checked={settings.autoApproveMembers}
            onChange={e => setSettings(s => s && { ...s, autoApproveMembers: e.target.checked })}
            className="accent-white"
          />
          Auto-approve new members
        </label>
        <Input
          label="Discord Server ID (optional)"
          id="discord"
          value={settings.discordServerId ?? ''}
          onChange={e => setSettings(s => s && { ...s, discordServerId: e.target.value || null })}
        />
        {error && <p className="text-xs text-red-400">{error}</p>}
        {success && <p className="text-xs text-text-muted">Settings saved.</p>}
        <Button type="submit" loading={saving}>Save Settings</Button>
      </form>
    </div>
  )
}
