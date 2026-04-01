jest.mock('@/lib/prisma', () => ({
  prisma: { auditLog: { create: jest.fn() } },
}))

import { prisma } from '@/lib/prisma'
import { createAuditLog } from '@/lib/audit'

const mockCreate = prisma.auditLog.create as jest.Mock

beforeEach(() => jest.clearAllMocks())

test('creates audit log entry with all fields', async () => {
  mockCreate.mockResolvedValue({ id: 'log1' })
  await createAuditLog('c1', 'u1', 'MEMBER_PROMOTED', 'CommunityMember', 'm1', { from: 'MEMBER', to: 'ADMIN' })
  expect(mockCreate).toHaveBeenCalledWith({
    data: {
      communityId: 'c1',
      actorId: 'u1',
      action: 'MEMBER_PROMOTED',
      targetType: 'CommunityMember',
      targetId: 'm1',
      metadata: { from: 'MEMBER', to: 'ADMIN' },
    },
  })
})

test('creates audit log entry with only required fields', async () => {
  mockCreate.mockResolvedValue({ id: 'log2' })
  await createAuditLog('c1', 'u1', 'SETTINGS_UPDATED')
  expect(mockCreate).toHaveBeenCalledWith({
    data: {
      communityId: 'c1',
      actorId: 'u1',
      action: 'SETTINGS_UPDATED',
      targetType: undefined,
      targetId: undefined,
      metadata: undefined,
    },
  })
})

test('does not throw if create fails (fire-and-forget)', async () => {
  mockCreate.mockRejectedValue(new Error('DB error'))
  await expect(createAuditLog('c1', 'u1', 'TEST')).resolves.not.toThrow()
})
