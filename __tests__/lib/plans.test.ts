import { PLANS, checkPlanLimit, checkFeatureAccess, PlanLimitError, FeatureGatedError } from '@/lib/plans'

test('FREE plan has member cap of 15', () => {
  expect(PLANS.FREE.limits.members).toBe(15)
})

test('STANDARD plan has member cap of 75', () => {
  expect(PLANS.STANDARD.limits.members).toBe(75)
})

test('PRO plan has null member cap (unlimited)', () => {
  expect(PLANS.PRO.limits.members).toBeNull()
})

test('checkPlanLimit throws PlanLimitError when cap exceeded', () => {
  expect(() => checkPlanLimit('FREE', 'members', 15)).toThrow(PlanLimitError)
})

test('checkPlanLimit does not throw when under cap', () => {
  expect(() => checkPlanLimit('FREE', 'members', 14)).not.toThrow()
})

test('checkPlanLimit never throws for PRO unlimited', () => {
  expect(() => checkPlanLimit('PRO', 'members', 99999)).not.toThrow()
})

test('checkFeatureAccess throws FeatureGatedError for gated feature on FREE', () => {
  expect(() => checkFeatureAccess('FREE', 'patrolLogs')).toThrow(FeatureGatedError)
})

test('checkFeatureAccess allows patrolLogs on STANDARD', () => {
  expect(() => checkFeatureAccess('STANDARD', 'patrolLogs')).not.toThrow()
})

test('checkFeatureAccess throws for quizzes on STANDARD', () => {
  expect(() => checkFeatureAccess('STANDARD', 'quizzes')).toThrow(FeatureGatedError)
})

test('checkFeatureAccess allows quizzes on PRO', () => {
  expect(() => checkFeatureAccess('PRO', 'quizzes')).not.toThrow()
})

test('FREE plan has department cap of 1', () => {
  expect(PLANS.FREE.limits.departments).toBe(1)
})

test('STANDARD plan has department cap of 5', () => {
  expect(PLANS.STANDARD.limits.departments).toBe(5)
})

test('checkPlanLimit throws PlanLimitError for departments when cap exceeded', () => {
  expect(() => checkPlanLimit('FREE', 'departments', 1)).toThrow(PlanLimitError)
})

test('checkPlanLimit never throws for PRO unlimited departments', () => {
  expect(() => checkPlanLimit('PRO', 'departments', 99999)).not.toThrow()
})
