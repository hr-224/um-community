'use client'
import { createContext, useContext, ReactNode } from 'react'
import { useRouter } from 'next/navigation'
import type { CommunityInfo } from '@/types/community'

interface CommunityContextValue {
  community: CommunityInfo
  communities: CommunityInfo[]
  switchCommunity: (id: string) => Promise<void>
}

const CommunityCtx = createContext<CommunityContextValue | null>(null)

export function useCommunity(): CommunityContextValue {
  const ctx = useContext(CommunityCtx)
  if (!ctx) throw new Error('useCommunity must be used within CommunityProvider')
  return ctx
}

export function CommunityProvider({
  children,
  initialCommunity,
  initialCommunities,
}: {
  children: ReactNode
  initialCommunity: CommunityInfo
  initialCommunities: CommunityInfo[]
}) {
  const router = useRouter()

  async function switchCommunity(id: string) {
    await fetch('/api/community/switch', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ communityId: id }),
    })
    router.refresh()
  }

  return (
    <CommunityCtx.Provider value={{ community: initialCommunity, communities: initialCommunities, switchCommunity }}>
      {children}
    </CommunityCtx.Provider>
  )
}
