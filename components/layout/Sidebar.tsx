'use client'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'

interface NavItem { label: string; href: string }
interface NavGroup { label: string; items: NavItem[] }

const navigation: NavGroup[] = [
  {
    label: 'General',
    items: [
      { label: 'Dashboard',     href: '/dashboard' },
      { label: 'Announcements', href: '/announcements' },
      { label: 'Events',        href: '/events' },
      { label: 'Messages',      href: '/messages' },
    ],
  },
  {
    label: 'Personnel',
    items: [
      { label: 'Roster',           href: '/roster' },
      { label: 'Departments',      href: '/admin/departments' },
      { label: 'Chain of Command', href: '/chain-of-command' },
      { label: 'Applications',     href: '/admin/applications' },
      { label: 'Transfers',        href: '/transfers' },
    ],
  },
  {
    label: 'Operations',
    items: [
      { label: 'Patrol Logs', href: '/patrol-logs' },
      { label: 'Shifts',      href: '/shifts' },
      { label: 'SOPs',        href: '/sops' },
      { label: 'Documents',   href: '/documents' },
      { label: 'LOA',         href: '/loa' },
    ],
  },
]

export function Sidebar() {
  const pathname = usePathname()
  return (
    <aside className="w-[220px] bg-bg-base border-r border-border-default flex flex-col gap-5 px-2.5 py-4 overflow-y-auto flex-shrink-0">
      {navigation.map((group) => (
        <div key={group.label}>
          <p className="text-[10px] text-text-faint uppercase tracking-[1px] px-2 mb-1">
            {group.label}
          </p>
          {group.items.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              aria-current={pathname === item.href ? 'page' : undefined}
              className={cn(
                'flex items-center gap-2 px-2 py-1.5 rounded text-xs transition-colors mb-0.5',
                pathname === item.href
                  ? 'bg-bg-elevated text-text-primary'
                  : 'text-text-muted hover:bg-bg-elevated hover:text-text-secondary'
              )}
            >
              {item.label}
            </Link>
          ))}
        </div>
      ))}
    </aside>
  )
}
