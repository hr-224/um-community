import { NextResponse } from 'next/server'
import { z } from 'zod'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { createCheckoutSession } from '@/lib/stripe'
import { PlanTier } from '@/lib/generated/prisma/client'

const schema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters').max(60),
  planTier: z.enum(['FREE', 'STANDARD', 'PRO']),
})

function slugify(name: string): string {
  return name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
}

async function uniqueSlug(base: string): Promise<string> {
  let slug = slugify(base)
  let i = 0
  while (await prisma.community.findFirst({ where: { slug } })) {
    slug = `${slugify(base)}-${++i}`
  }
  return slug
}

export async function POST(req: Request) {
  const session = await auth()
  if (!session?.user?.id) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })

  try {
    const body = await req.json()
    const parsed = schema.safeParse(body)
    if (!parsed.success) {
      return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
    }

    const { name, planTier } = parsed.data
    const slug = await uniqueSlug(name)

    if (planTier === 'FREE') {
      const community = await prisma.community.create({
        data: {
          name,
          slug,
          ownerId: session.user.id,
          planTier: PlanTier.FREE,
          status: 'ACTIVE',
        },
      })
      await prisma.communityMember.create({
        data: { communityId: community.id, userId: session.user.id, role: 'OWNER' },
      })
      return NextResponse.json({ community }, { status: 201 })
    }

    // Paid plan — redirect to Stripe Checkout
    const checkoutUrl = await createCheckoutSession({
      userId: session.user.id,
      email: session.user.email!,
      planTier: planTier as 'STANDARD' | 'PRO',
      communityName: name,
      successUrl: `${process.env.NEXT_PUBLIC_APP_URL}/onboarding?success=1`,
      cancelUrl: `${process.env.NEXT_PUBLIC_APP_URL}/onboarding`,
    })

    return NextResponse.json({ checkoutUrl })
  } catch {
    return NextResponse.json({ error: 'Something went wrong' }, { status: 500 })
  }
}
