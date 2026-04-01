import { NextResponse } from 'next/server'
import { stripe } from '@/lib/stripe'
import { prisma } from '@/lib/prisma'
import { PlanTier } from '@/lib/generated/prisma/client'
import Stripe from 'stripe'

export async function POST(req: Request) {
  const body = await req.text()
  const sig = req.headers.get('stripe-signature')!

  let event: Stripe.Event
  try {
    event = stripe.webhooks.constructEvent(body, sig, process.env.STRIPE_WEBHOOK_SECRET!)
  } catch {
    return NextResponse.json({ error: 'Invalid signature' }, { status: 400 })
  }

  if (event.type === 'checkout.session.completed') {
    const session = event.data.object as Stripe.Checkout.Session
    const { userId, communityName, planTier } = session.metadata!

    const slug = communityName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')

    const community = await prisma.community.create({
      data: {
        name: communityName,
        slug,
        ownerId: userId,
        planTier: planTier as PlanTier,
        stripeCustomerId: session.customer as string,
        stripeSubscriptionId: session.subscription as string,
        subscriptionStatus: 'active',
        status: 'ACTIVE',
      },
    })

    await prisma.communityMember.create({
      data: { communityId: community.id, userId, role: 'OWNER' },
    })

    const sub = await stripe.subscriptions.retrieve(session.subscription as string)
    await prisma.subscription.create({
      data: {
        communityId: community.id,
        stripeSubscriptionId: sub.id,
        stripePriceId: sub.items.data[0].price.id,
        planTier: planTier as PlanTier,
        status: sub.status,
        currentPeriodStart: new Date(sub.current_period_start * 1000),
        currentPeriodEnd: new Date(sub.current_period_end * 1000),
      },
    })
  }

  if (event.type === 'customer.subscription.updated') {
    const sub = event.data.object as Stripe.Subscription
    await prisma.subscription.update({
      where: { stripeSubscriptionId: sub.id },
      data: {
        status: sub.status,
        cancelAtPeriodEnd: sub.cancel_at_period_end,
        currentPeriodEnd: new Date(sub.current_period_end * 1000),
      },
    })
  }

  if (event.type === 'customer.subscription.deleted') {
    const sub = event.data.object as Stripe.Subscription
    await prisma.community.updateMany({
      where: { stripeSubscriptionId: sub.id },
      data: { status: 'CANCELLED' },
    })
  }

  if (event.type === 'invoice.payment_failed') {
    const invoice = event.data.object as Stripe.Invoice
    await prisma.community.updateMany({
      where: { stripeCustomerId: invoice.customer as string },
      data: { subscriptionStatus: 'past_due' },
    })
  }

  return NextResponse.json({ received: true })
}
