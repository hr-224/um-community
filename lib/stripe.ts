import Stripe from 'stripe'
import { PlanTier } from './generated/prisma/client'

export const stripe = new Stripe(process.env.STRIPE_SECRET_KEY!, {
  apiVersion: '2025-01-27.acacia',
})

export function getPriceIdForPlan(tier: PlanTier): string | null {
  if (tier === 'FREE') return null
  if (tier === 'STANDARD') return process.env.STRIPE_STANDARD_PRICE_ID ?? null
  if (tier === 'PRO') return process.env.STRIPE_PRO_PRICE_ID ?? null
  return null
}

export async function createCheckoutSession({
  userId,
  email,
  planTier,
  communityName,
  successUrl,
  cancelUrl,
}: {
  userId: string
  email: string
  planTier: 'STANDARD' | 'PRO'
  communityName: string
  successUrl: string
  cancelUrl: string
}): Promise<string> {
  const priceId = getPriceIdForPlan(planTier)
  if (!priceId) throw new Error('No price ID for plan')

  const session = await stripe.checkout.sessions.create({
    mode: 'subscription',
    payment_method_types: ['card'],
    customer_email: email,
    line_items: [{ price: priceId, quantity: 1 }],
    metadata: { userId, communityName, planTier },
    success_url: successUrl,
    cancel_url: cancelUrl,
  })

  return session.url!
}
