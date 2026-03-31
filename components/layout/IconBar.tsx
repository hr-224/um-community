'use client'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import {
  LayoutDashboard, Users, FileText, Calendar,
  ClipboardList, Settings
} from 'lucide-react'

const icons = [
  { icon: LayoutDashboard, href: '/dashboard', label: 'Dashboard' },
  { icon: Users,           href: '/roster',    label: 'Roster' },
  { icon: FileText,        href: '/sops',       label: 'SOPs' },
  { icon: Calendar,        href: '/events',     label: 'Events' },
  { icon: ClipboardList,   href: '/patrol-logs',label: 'Patrol Logs' },
]

export function IconBar() {
  const pathname = usePathname()
  return (
    <aside className="w-[60px] bg-[#080808] border-r border-border-default flex flex-col items-center py-3 gap-1 flex-shrink-0">
      {/* Community switcher placeholder — Phase 2 */}
      <div className="w-8 h-8 rounded-lg bg-white mb-3 flex-shrink-0" />
      <div className="w-6 h-px bg-border-default mb-1" />

      {icons.map(({ icon: Icon, href, label }) => (
        <Link
          key={href}
          href={href}
          title={label}
          className={cn(
            'w-9 h-9 rounded-lg flex items-center justify-center transition-colors',
            pathname.startsWith(href)
              ? 'bg-bg-elevated text-text-primary'
              : 'text-text-faint hover:text-text-muted hover:bg-bg-elevated'
          )}
        >
          <Icon size={16} />
        </Link>
      ))}

      <div className="flex-1" />
      <Link href="/admin" title="Settings" className="w-9 h-9 rounded-lg flex items-center justify-center text-text-faint hover:text-text-muted hover:bg-bg-elevated transition-colors">
        <Settings size={16} />
      </Link>
    </aside>
  )
}
