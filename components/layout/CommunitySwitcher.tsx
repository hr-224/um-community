'use client'
import { useState, useRef, useEffect } from 'react'
import { useCommunity } from '@/components/providers/CommunityProvider'
import { cn } from '@/lib/utils'

export function CommunitySwitcher() {
  const { community, communities, switchCommunity } = useCommunity()
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    function onClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  return (
    <div ref={ref} className="relative mb-3">
      <button
        onClick={() => setOpen(o => !o)}
        aria-label={`Active community: ${community.name}`}
        title={community.name}
        className="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-black text-xs font-bold flex-shrink-0 hover:opacity-90 transition-opacity"
      >
        {community.logo
          ? <img src={community.logo} alt={community.name} className="w-full h-full rounded-lg object-cover" />
          : community.name[0]?.toUpperCase() ?? '?'}
      </button>

      {open && (
        <div className="absolute left-12 top-0 z-50 w-56 bg-bg-elevated border border-border-default rounded-lg shadow-xl py-1">
          <p className="text-[10px] text-text-faint uppercase tracking-wider px-3 py-1.5">Communities</p>
          {communities.map(c => (
            <button
              key={c.id}
              onClick={() => { switchCommunity(c.id); setOpen(false) }}
              className={cn(
                'w-full text-left px-3 py-2 text-xs flex items-center gap-2.5 transition-colors',
                c.id === community.id
                  ? 'text-text-primary bg-bg-surface'
                  : 'text-text-muted hover:text-text-secondary hover:bg-bg-surface'
              )}
            >
              <span className="w-5 h-5 rounded bg-white text-black text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                {c.name[0]?.toUpperCase()}
              </span>
              <span className="truncate">{c.name}</span>
              {c.id === community.id && <span className="ml-auto text-[10px] text-text-faint">active</span>}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
