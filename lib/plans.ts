import { PlanTier } from './generated/prisma/client'

export class PlanLimitError extends Error {
  constructor(public limitType: string, public tier: string) {
    super(`Plan limit reached: ${limitType} on ${tier} tier`)
    this.name = 'PlanLimitError'
  }
}

export class FeatureGatedError extends Error {
  constructor(public feature: string, public tier: string) {
    super(`Feature '${feature}' is not available on ${tier} tier`)
    this.name = 'FeatureGatedError'
  }
}

interface PlanConfig {
  limits: {
    members: number | null
    departments: number | null
  }
  features: Set<string>
}

export const PLANS: Record<PlanTier, PlanConfig> = {
  FREE: {
    limits: { members: 15, departments: 1 },
    features: new Set([
      'announcements', 'roster', 'applications', 'events', 'messages',
    ]),
  },
  STANDARD: {
    limits: { members: 75, departments: 5 },
    features: new Set([
      'announcements', 'roster', 'applications', 'events', 'messages',
      'patrolLogs', 'shifts', 'sops', 'documents', 'loa', 'chainOfCommand',
      'transfers', 'discordIntegration',
    ]),
  },
  PRO: {
    limits: { members: null, departments: null },
    features: new Set([
      'announcements', 'roster', 'applications', 'events', 'messages',
      'patrolLogs', 'shifts', 'sops', 'documents', 'loa', 'chainOfCommand',
      'transfers', 'discordIntegration',
      'quizzes', 'analytics', 'customFields', 'mentorships', 'recognition',
      'auditLog', 'apiKeys',
    ]),
  },
}

export function checkPlanLimit(
  tier: PlanTier,
  limitType: 'members' | 'departments',
  currentCount: number
): void {
  const limit = PLANS[tier].limits[limitType]
  if (limit !== null && currentCount >= limit) {
    throw new PlanLimitError(limitType, tier)
  }
}

export function checkFeatureAccess(tier: PlanTier, feature: string): void {
  if (!PLANS[tier].features.has(feature)) {
    throw new FeatureGatedError(feature, tier)
  }
}
