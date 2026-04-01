import { Prisma } from '@/lib/generated/prisma/client'
import { prisma } from '@/lib/prisma'

export async function createAuditLog(
  communityId: string,
  actorId: string,
  action: string,
  targetType?: string,
  targetId?: string,
  metadata?: Record<string, unknown>
): Promise<void> {
  try {
    await prisma.auditLog.create({
      data: {
        communityId,
        actorId,
        action,
        targetType,
        targetId,
        metadata: metadata as Prisma.InputJsonValue | undefined,
      },
    })
  } catch {
    // Audit log failures must never break the main request
  }
}
