import type { PlanTier, MemberRole } from '@/lib/generated/prisma/client'

export interface CommunityInfo {
  id: string
  name: string
  slug: string
  logo: string | null
  planTier: PlanTier
  role: MemberRole
}
