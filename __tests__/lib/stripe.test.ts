import { getPriceIdForPlan } from '@/lib/stripe'

test('getPriceIdForPlan returns correct env var for STANDARD', () => {
  process.env.STRIPE_STANDARD_PRICE_ID = 'price_standard_123'
  expect(getPriceIdForPlan('STANDARD')).toBe('price_standard_123')
})

test('getPriceIdForPlan returns correct env var for PRO', () => {
  process.env.STRIPE_PRO_PRICE_ID = 'price_pro_456'
  expect(getPriceIdForPlan('PRO')).toBe('price_pro_456')
})

test('getPriceIdForPlan returns null for FREE', () => {
  expect(getPriceIdForPlan('FREE')).toBeNull()
})
