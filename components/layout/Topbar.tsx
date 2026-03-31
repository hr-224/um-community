'use client'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { Bell, Search } from 'lucide-react'

export function Topbar() {
  const pathname = usePathname()
  const crumb = pathname.split('/').filter(Boolean).join(' / ')

  return (
    <header className="h-12 bg-bg-base border-b border-border-default flex items-center justify-between px-5 flex-shrink-0">
      <p className="text-xs text-text-faint">
        {crumb || 'dashboard'}
      </p>
      <div className="flex items-center gap-2.5">
        <div className="flex items-center gap-2 bg-bg-elevated border border-border-default rounded px-3 py-1.5 w-44">
          <Search size={12} className="text-text-faint" aria-hidden="true" />
          <input
            type="search"
            placeholder="Search..."
            readOnly
            className="bg-transparent text-xs text-text-faint outline-none w-full placeholder:text-text-faint"
          />
        </div>
        <button aria-label="Notifications" className="w-7 h-7 flex items-center justify-center text-text-faint hover:text-text-secondary transition-colors">
          <Bell size={15} />
        </button>
        <Link href="/account" aria-label="Account settings" className="w-7 h-7 rounded-full bg-bg-elevated border border-border-default" />
      </div>
    </header>
  )
}
