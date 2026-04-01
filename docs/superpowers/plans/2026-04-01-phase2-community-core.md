# Phase 2: Community Context + Core Features — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Wire community context (switcher, auth wrapper, layout), then implement all-tier feature pages: Dashboard, Announcements, Events, Roster, Messages, plus admin panel basics (Applications, Members, Departments, Settings) and invite link join flow.

**Architecture:** A server component `app/(app)/layout.tsx` fetches the active user's community memberships, resolves the active community from a cookie, and injects data into a `CommunityProvider` client context. API routes are wrapped in `withCommunityAuth()` which reads the same cookie and validates membership. All admin-only routes pass `{ adminOnly: true }` to the wrapper.

**Tech Stack:** Next.js 16 App Router, Prisma 7 (`@/lib/generated/prisma/client`), NextAuth v5, Zod v4 (`issues[0]`), `next/headers` cookies (async API), `nanoid` for invite codes.

**Critical reminders for implementers:**
- Prisma 7: always import types/enums from `@/lib/generated/prisma/client`, never `@prisma/client`
- Zod v4: `parsed.error.issues[0].message` not `.errors[0]`
- `cookies()` from `next/headers` is async — always `await cookies()`
- `useSearchParams` always needs a Suspense wrapper
- Read `node_modules/next/dist/docs/` before writing any App Router page

---

## File Map

**New lib utilities:**
- `lib/community-auth.ts` — `withCommunityAuth(handler, opts?)` wrapper + `CommunityContext` type
- `lib/audit.ts` — `createAuditLog(communityId, actorId, action, targetType?, targetId?, metadata?)` helper

**New providers/context:**
- `components/providers/CommunityProvider.tsx` — `CommunityProvider` + `useCommunity()` hook
- `types/community.ts` — shared `CommunityInfo` interface

**Modified layout:**
- `app/(app)/layout.tsx` — server component: fetch memberships, resolve active community, wrap in CommunityProvider
- `middleware.ts` — add community-zone redirect to `/onboarding` when no cookie + no memberships (keep as is; layout handles it)

**New API routes:**
- `app/api/community/switch/route.ts` — POST: set `active_community_id` cookie
- `app/api/dashboard/stats/route.ts` — GET: member count, pending apps, dept breakdown
- `app/api/announcements/route.ts` — GET list + POST create
- `app/api/events/route.ts` — GET list + POST create
- `app/api/roster/route.ts` — GET members with dept/rank
- `app/api/messages/route.ts` — GET inbox + POST send
- `app/api/admin/applications/route.ts` — GET list
- `app/api/admin/applications/[id]/route.ts` — PATCH approve/deny
- `app/api/admin/members/[id]/route.ts` — PATCH update role/dept/rank/status + DELETE remove
- `app/api/admin/departments/route.ts` — GET list + POST create
- `app/api/admin/departments/[id]/route.ts` — PATCH update + DELETE
- `app/api/admin/departments/[id]/ranks/route.ts` — POST create rank
- `app/api/admin/ranks/[id]/route.ts` — PATCH update + DELETE rank
- `app/api/admin/settings/route.ts` — GET + PATCH community settings
- `app/api/admin/invites/route.ts` — POST create invite link
- `app/api/community/join/route.ts` — POST join via invite code

**New UI components:**
- `components/layout/CommunitySwitcher.tsx` — popover listing user's communities

**Modified UI:**
- `components/layout/IconBar.tsx` — replace placeholder div with `<CommunitySwitcher />`

**New pages:**
- `app/(app)/dashboard/page.tsx` — stat cards + recent members
- `app/(app)/announcements/page.tsx` — pinned-first list
- `app/(app)/announcements/new/page.tsx` — create form (admin/mod)
- `app/(app)/events/page.tsx` — event list
- `app/(app)/events/new/page.tsx` — create form (admin/mod)
- `app/(app)/roster/page.tsx` — member directory with dept filter
- `app/(app)/messages/page.tsx` — inbox + compose
- `app/(app)/admin/applications/page.tsx` — review queue
- `app/(app)/admin/members/page.tsx` — member management table
- `app/(app)/admin/departments/page.tsx` — dept + rank management
- `app/(app)/admin/settings/page.tsx` — community settings form
- `app/onboarding/page.tsx` — update to add "Join via invite" tab

**Tests:**
- `__tests__/lib/community-auth.test.ts`
- `__tests__/lib/audit.test.ts`
- `__tests__/api/community-switch.test.ts`
- `__tests__/api/dashboard-stats.test.ts`
- `__tests__/api/announcements.test.ts`
- `__tests__/api/events.test.ts`
- `__tests__/api/roster.test.ts`
- `__tests__/api/messages.test.ts`
- `__tests__/api/admin-applications.test.ts`
- `__tests__/api/admin-members.test.ts`
- `__tests__/api/admin-departments.test.ts`
- `__tests__/api/admin-settings.test.ts`
- `__tests__/api/community-join.test.ts`

---

## Task 1: `withCommunityAuth` utility

**Files:**
- Create: `lib/community-auth.ts`
- Create: `types/community.ts`
- Test: `__tests__/lib/community-auth.test.ts`

- [ ] **Step 1: Create shared type file**

Create `types/community.ts`:

```typescript
import type { PlanTier, MemberRole } from '@/lib/generated/prisma/client'

export interface CommunityInfo {
  id: string
  name: string
  slug: string
  logo: string | null
  planTier: PlanTier
  role: MemberRole
}
```

- [ ] **Step 2: Write the failing tests**

Create `__tests__/lib/community-auth.test.ts`:

```typescript
import { withCommunityAuth } from '@/lib/community-auth'
import { NextResponse } from 'next/server'

jest.mock('@/lib/auth', () => ({ auth: jest.fn() }))
jest.mock('next/headers', () => ({ cookies: jest.fn() }))
jest.mock('@/lib/prisma', () => ({
  prisma: { communityMember: { findFirst: jest.fn() } },
}))

import { auth } from '@/lib/auth'
import { cookies } from 'next/headers'
import { prisma } from '@/lib/prisma'

const mockAuth = auth as jest.Mock
const mockCookies = cookies as jest.Mock
const mockFindFirst = prisma.communityMember.findFirst as jest.Mock

const fakeMember = {
  id: 'm1', communityId: 'c1', userId: 'u1',
  role: 'OWNER', status: 'ACTIVE',
  community: { id: 'c1', name: 'Test', planTier: 'FREE', status: 'ACTIVE', slug: 'test', logo: null },
}
const cookieStore = (val?: string) => ({
  get: (k: string) => k === 'active_community_id' && val ? { value: val } : undefined,
})

beforeEach(() => jest.clearAllMocks())

test('returns 401 when no session', async () => {
  mockAuth.mockResolvedValue(null)
  const res = await withCommunityAuth(jest.fn())(new Request('http://localhost'))
  expect(res.status).toBe(401)
})

test('returns 400 when no active_community_id cookie', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockCookies.mockResolvedValue(cookieStore())
  const res = await withCommunityAuth(jest.fn())(new Request('http://localhost'))
  expect(res.status).toBe(400)
})

test('returns 403 when not a member', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockCookies.mockResolvedValue(cookieStore('c1'))
  mockFindFirst.mockResolvedValue(null)
  const res = await withCommunityAuth(jest.fn())(new Request('http://localhost'))
  expect(res.status).toBe(403)
})

test('calls handler with correct context', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockCookies.mockResolvedValue(cookieStore('c1'))
  mockFindFirst.mockResolvedValue(fakeMember)
  const handler = jest.fn().mockResolvedValue(NextResponse.json({ ok: true }))
  await withCommunityAuth(handler)(new Request('http://localhost'))
  expect(handler).toHaveBeenCalledWith(
    expect.any(Request),
    { userId: 'u1', communityId: 'c1', community: fakeMember.community, member: fakeMember }
  )
})

test('returns 403 when adminOnly and role is MEMBER', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockCookies.mockResolvedValue(cookieStore('c1'))
  mockFindFirst.mockResolvedValue({ ...fakeMember, role: 'MEMBER' })
  const handler = jest.fn()
  const res = await withCommunityAuth(handler, { adminOnly: true })(new Request('http://localhost'))
  expect(res.status).toBe(403)
  expect(handler).not.toHaveBeenCalled()
})
```

- [ ] **Step 3: Run — expect FAIL**

```bash
npx jest __tests__/lib/community-auth.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 4: Implement `lib/community-auth.ts`**

```typescript
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { NextResponse } from 'next/server'
import { cookies } from 'next/headers'
import type { Community, CommunityMember } from '@/lib/generated/prisma/client'

export interface CommunityContext {
  userId: string
  communityId: string
  community: Community
  member: CommunityMember & { community: Community }
}

type CommunityHandler = (req: Request, ctx: CommunityContext) => Promise<Response>

const ADMIN_ROLES = new Set(['OWNER', 'ADMIN'])

export function withCommunityAuth(
  handler: CommunityHandler,
  opts?: { adminOnly?: boolean }
): (req: Request) => Promise<Response> {
  return async (req: Request) => {
    const session = await auth()
    if (!session?.user?.id) {
      return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })
    }
    const cookieStore = await cookies()
    const communityId = cookieStore.get('active_community_id')?.value
    if (!communityId) {
      return NextResponse.json({ error: 'No active community' }, { status: 400 })
    }
    const member = await prisma.communityMember.findFirst({
      where: { communityId, userId: session.user.id, status: 'ACTIVE' },
      include: { community: true },
    })
    if (!member) {
      return NextResponse.json({ error: 'Forbidden' }, { status: 403 })
    }
    if (opts?.adminOnly && !ADMIN_ROLES.has(member.role)) {
      return NextResponse.json({ error: 'Forbidden' }, { status: 403 })
    }
    return handler(req, { userId: session.user.id, communityId, community: member.community, member })
  }
}
```

- [ ] **Step 5: Run — expect PASS**

```bash
npx jest __tests__/lib/community-auth.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 6: Commit**

```bash
git add types/community.ts lib/community-auth.ts __tests__/lib/community-auth.test.ts
git commit -m "feat: add withCommunityAuth wrapper and CommunityInfo type"
```

---

## Task 2: Community switch API + CommunityProvider

**Files:**
- Create: `app/api/community/switch/route.ts`
- Create: `components/providers/CommunityProvider.tsx`
- Test: `__tests__/api/community-switch.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/api/community-switch.test.ts`:

```typescript
import { POST } from '@/app/api/community/switch/route'

jest.mock('@/lib/auth', () => ({ auth: jest.fn() }))
jest.mock('@/lib/prisma', () => ({
  prisma: { communityMember: { findFirst: jest.fn() } },
}))
jest.mock('next/headers', () => ({ cookies: jest.fn() }))

import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { cookies } from 'next/headers'

const mockAuth = auth as jest.Mock
const mockFindFirst = prisma.communityMember.findFirst as jest.Mock
const mockCookies = cookies as jest.Mock
const mockCookieStore = { set: jest.fn() }

beforeEach(() => { jest.clearAllMocks(); mockCookies.mockResolvedValue(mockCookieStore) })

function req(body: object) {
  return new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
  })
}

test('returns 401 if not authenticated', async () => {
  mockAuth.mockResolvedValue(null)
  expect((await POST(req({ communityId: 'c1' }))).status).toBe(401)
})

test('returns 400 if communityId missing', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  expect((await POST(req({}))).status).toBe(400)
})

test('returns 403 if not a member', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockFindFirst.mockResolvedValue(null)
  expect((await POST(req({ communityId: 'c1' }))).status).toBe(403)
})

test('sets cookie and returns ok when valid', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockFindFirst.mockResolvedValue({ id: 'm1' })
  const res = await POST(req({ communityId: 'c1' }))
  expect(res.status).toBe(200)
  expect(mockCookieStore.set).toHaveBeenCalledWith(
    'active_community_id', 'c1', expect.objectContaining({ httpOnly: true })
  )
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/community-switch.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement switch route**

Create `app/api/community/switch/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { cookies } from 'next/headers'

export async function POST(req: Request) {
  const session = await auth()
  if (!session?.user?.id) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })

  const body = await req.json()
  const communityId: string | undefined = body?.communityId
  if (!communityId) return NextResponse.json({ error: 'communityId required' }, { status: 400 })

  const member = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!member) return NextResponse.json({ error: 'Forbidden' }, { status: 403 })

  const cookieStore = await cookies()
  cookieStore.set('active_community_id', communityId, {
    httpOnly: true, sameSite: 'lax', path: '/', maxAge: 60 * 60 * 24 * 30,
  })

  return NextResponse.json({ ok: true })
}
```

- [ ] **Step 4: Create CommunityProvider**

Create `components/providers/CommunityProvider.tsx`:

```typescript
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
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
npx jest __tests__/api/community-switch.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 6: Commit**

```bash
git add app/api/community/switch/route.ts components/providers/CommunityProvider.tsx __tests__/api/community-switch.test.ts
git commit -m "feat: add community switch API and CommunityProvider context"
```

---

## Task 3: App layout + CommunitySwitcher + IconBar

**Files:**
- Modify: `app/(app)/layout.tsx`
- Create: `components/layout/CommunitySwitcher.tsx`
- Modify: `components/layout/IconBar.tsx`

No unit tests for these (pure UI/layout).

- [ ] **Step 1: Update `app/(app)/layout.tsx`**

Replace the entire file with:

```typescript
import { redirect } from 'next/navigation'
import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { AppShell } from '@/components/layout/AppShell'
import { CommunityProvider } from '@/components/providers/CommunityProvider'
import type { CommunityInfo } from '@/types/community'
import type { ReactNode } from 'react'

export default async function AppLayout({ children }: { children: ReactNode }) {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')

  const memberships = await prisma.communityMember.findMany({
    where: { userId: session.user.id, status: 'ACTIVE' },
    include: { community: true },
    orderBy: { joinedAt: 'asc' },
  })

  if (!memberships.length) redirect('/onboarding')

  const cookieStore = await cookies()
  const activeCommunityId = cookieStore.get('active_community_id')?.value
  const activeMembership =
    memberships.find(m => m.communityId === activeCommunityId) ?? memberships[0]

  const communities: CommunityInfo[] = memberships.map(m => ({
    id: m.communityId,
    name: m.community.name,
    slug: m.community.slug,
    logo: m.community.logo,
    planTier: m.community.planTier,
    role: m.role,
  }))

  const activeCommunity: CommunityInfo = {
    id: activeMembership.communityId,
    name: activeMembership.community.name,
    slug: activeMembership.community.slug,
    logo: activeMembership.community.logo,
    planTier: activeMembership.community.planTier,
    role: activeMembership.role,
  }

  return (
    <CommunityProvider initialCommunity={activeCommunity} initialCommunities={communities}>
      <AppShell>{children}</AppShell>
    </CommunityProvider>
  )
}
```

- [ ] **Step 2: Create CommunitySwitcher**

Create `components/layout/CommunitySwitcher.tsx`:

```typescript
'use client'
import { useState, useRef, useEffect } from 'react'
import { useCommunity } from '@/components/providers/CommunityProvider'
import { cn } from '@/lib/utils'

export function CommunitySwitcher() {
  const { community, communities, switchCommunity } = useCommunity()
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    function onClick(e: MouseEvent) {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  return (
    <div ref={ref} className="relative mb-3">
      <button
        onClick={() => setOpen(o => !o)}
        aria-label={`Active community: ${community.name}`}
        title={community.name}
        className="w-8 h-8 rounded-lg bg-white flex items-center justify-center text-black text-xs font-bold flex-shrink-0 hover:opacity-90 transition-opacity"
      >
        {community.logo
          ? <img src={community.logo} alt={community.name} className="w-full h-full rounded-lg object-cover" />
          : community.name[0]?.toUpperCase() ?? '?'}
      </button>

      {open && (
        <div className="absolute left-12 top-0 z-50 w-56 bg-bg-elevated border border-border-default rounded-lg shadow-xl py-1">
          <p className="text-[10px] text-text-faint uppercase tracking-wider px-3 py-1.5">Communities</p>
          {communities.map(c => (
            <button
              key={c.id}
              onClick={() => { switchCommunity(c.id); setOpen(false) }}
              className={cn(
                'w-full text-left px-3 py-2 text-xs flex items-center gap-2.5 transition-colors',
                c.id === community.id
                  ? 'text-text-primary bg-bg-surface'
                  : 'text-text-muted hover:text-text-secondary hover:bg-bg-surface'
              )}
            >
              <span className="w-5 h-5 rounded bg-white text-black text-[10px] font-bold flex items-center justify-center flex-shrink-0">
                {c.name[0]?.toUpperCase()}
              </span>
              <span className="truncate">{c.name}</span>
              {c.id === community.id && <span className="ml-auto text-[10px] text-text-faint">active</span>}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
```

- [ ] **Step 3: Update IconBar**

In `components/layout/IconBar.tsx`:

1. Add import after existing imports:
```typescript
import { CommunitySwitcher } from './CommunitySwitcher'
```

2. Replace the placeholder div:
```typescript
      {/* Community switcher placeholder — Phase 2 */}
      <div className="w-8 h-8 rounded-lg bg-white mb-3 flex-shrink-0" />
```
with:
```typescript
      <CommunitySwitcher />
```

- [ ] **Step 4: TypeScript check**

```bash
npx tsc --noEmit 2>&1 | head -30
```

Fix any errors before committing.

- [ ] **Step 5: Commit**

```bash
git add app/'(app)'/layout.tsx components/layout/CommunitySwitcher.tsx components/layout/IconBar.tsx
git commit -m "feat: wire app layout with CommunityProvider, add CommunitySwitcher"
```

---

## Task 4: Dashboard

**Files:**
- Create: `app/api/dashboard/stats/route.ts`
- Modify: `app/(app)/dashboard/page.tsx`
- Test: `__tests__/api/dashboard-stats.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/api/dashboard-stats.test.ts`:

```typescript
import { GET } from '@/app/api/dashboard/stats/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    communityMember: { count: jest.fn() },
    application: { count: jest.fn() },
    department: { findMany: jest.fn() },
  },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const ctx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1', name: 'Test', planTier: 'FREE' } as any,
  member: { role: 'OWNER' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('returns dashboard stats', async () => {
  ;(prisma.communityMember.count as jest.Mock).mockResolvedValue(10)
  ;(prisma.application.count as jest.Mock).mockResolvedValue(3)
  ;(prisma.department.findMany as jest.Mock).mockResolvedValue([
    { id: 'd1', name: 'Police', _count: { members: 5 } },
  ])

  const res = await GET(new Request('http://localhost'), ctx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.memberCount).toBe(10)
  expect(json.pendingApplications).toBe(3)
  expect(json.departments).toHaveLength(1)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/dashboard-stats.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement stats route**

Create `app/api/dashboard/stats/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const { communityId } = ctx

  const [memberCount, pendingApplications, departments] = await Promise.all([
    prisma.communityMember.count({ where: { communityId, status: 'ACTIVE' } }),
    prisma.application.count({ where: { communityId, status: 'PENDING' } }),
    prisma.department.findMany({
      where: { communityId },
      include: { _count: { select: { members: true } } },
      orderBy: { sortOrder: 'asc' },
    }),
  ])

  return NextResponse.json({ memberCount, pendingApplications, departments })
})
```

- [ ] **Step 4: Implement dashboard page**

Replace `app/(app)/dashboard/page.tsx`:

```typescript
import { cn } from '@/lib/utils'

interface Stats {
  memberCount: number
  pendingApplications: number
  departments: Array<{ id: string; name: string; _count: { members: number } }>
}

async function getStats(communityId: string): Promise<Stats | null> {
  try {
    const res = await fetch(`${process.env.NEXT_PUBLIC_APP_URL}/api/dashboard/stats`, {
      cache: 'no-store',
      headers: { cookie: `active_community_id=${communityId}` },
    })
    if (!res.ok) return null
    return res.json()
  } catch {
    return null
  }
}

// Note: In App Router server components, use the Prisma client directly for server-side fetching
// rather than calling your own API. Replace getStats with a direct Prisma call in production.
// For this phase, import prisma and query directly:

import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function DashboardPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')

  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const [memberCount, pendingApplications, departments, recentMembers] = await Promise.all([
    prisma.communityMember.count({ where: { communityId, status: 'ACTIVE' } }),
    prisma.application.count({ where: { communityId, status: 'PENDING' } }),
    prisma.department.findMany({
      where: { communityId },
      include: { _count: { select: { members: true } } },
      orderBy: { sortOrder: 'asc' },
    }),
    prisma.communityMember.findMany({
      where: { communityId, status: 'ACTIVE' },
      include: { user: { select: { email: true, avatar: true } }, department: true, rank: true },
      orderBy: { joinedAt: 'desc' },
      take: 5,
    }),
  ])

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight mb-6">Dashboard</h1>

      <div className="grid grid-cols-2 gap-4 mb-8">
        <div className="bg-bg-surface border border-border-default rounded-lg p-4 border-t-2 border-t-white">
          <p className="text-[11px] uppercase tracking-[0.8px] text-text-muted mb-1">Members</p>
          <p className="text-2xl font-semibold">{memberCount}</p>
        </div>
        <div className={cn(
          'bg-bg-surface border border-border-default rounded-lg p-4',
          pendingApplications > 0 ? 'border-t-2 border-t-white' : ''
        )}>
          <p className="text-[11px] uppercase tracking-[0.8px] text-text-muted mb-1">Pending Applications</p>
          <p className="text-2xl font-semibold">{pendingApplications}</p>
        </div>
      </div>

      {departments.length > 0 && (
        <div className="mb-8">
          <p className="text-[11px] uppercase tracking-[0.8px] text-text-muted mb-3">Departments</p>
          <div className="flex flex-col gap-1">
            {departments.map(dept => (
              <div key={dept.id} className="flex items-center justify-between py-2 border-b border-border-default last:border-0">
                <span className="text-sm text-text-secondary">{dept.name}</span>
                <span className="text-xs text-text-muted">{dept._count.members} members</span>
              </div>
            ))}
          </div>
        </div>
      )}

      {recentMembers.length > 0 && (
        <div>
          <p className="text-[11px] uppercase tracking-[0.8px] text-text-muted mb-3">Recent Members</p>
          <div className="flex flex-col gap-1">
            {recentMembers.map(m => (
              <div key={m.id} className="flex items-center gap-3 py-2 border-b border-border-default last:border-0">
                <div className="w-7 h-7 rounded-full bg-bg-elevated flex items-center justify-center text-xs text-text-muted flex-shrink-0">
                  {m.user.email[0]?.toUpperCase()}
                </div>
                <div className="min-w-0">
                  <p className="text-sm text-text-secondary truncate">{m.user.email}</p>
                  {m.department && <p className="text-xs text-text-muted">{m.department.name}{m.rank ? ` · ${m.rank.name}` : ''}</p>}
                </div>
                <span className="ml-auto text-[10px] text-text-muted capitalize">{m.role.toLowerCase()}</span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  )
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
npx jest __tests__/api/dashboard-stats.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 6: Commit**

```bash
git add app/api/dashboard/stats/route.ts app/'(app)'/dashboard/page.tsx __tests__/api/dashboard-stats.test.ts
git commit -m "feat: add dashboard stats API and dashboard page"
```

---

## Task 5: Announcements

**Files:**
- Create: `app/api/announcements/route.ts`
- Create: `app/(app)/announcements/page.tsx`
- Create: `app/(app)/announcements/new/page.tsx`
- Test: `__tests__/api/announcements.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/api/announcements.test.ts`:

```typescript
import { GET, POST } from '@/app/api/announcements/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { announcement: { findMany: jest.fn(), create: jest.fn() } },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1', planTier: 'FREE' } as any,
  member: { role: 'ADMIN' } as any,
}
const memberCtx: CommunityContext = { ...adminCtx, member: { role: 'MEMBER' } as any }

beforeEach(() => jest.clearAllMocks())

test('GET returns announcements pinned first', async () => {
  const rows = [
    { id: 'a1', title: 'Pinned', isPinned: true },
    { id: 'a2', title: 'Normal', isPinned: false },
  ]
  ;(prisma.announcement.findMany as jest.Mock).mockResolvedValue(rows)
  const res = await GET(new Request('http://localhost'), adminCtx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.announcements[0].isPinned).toBe(true)
})

test('POST returns 403 for MEMBER role', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Hello', content: 'World' }),
  })
  const res = await POST(req, memberCtx)
  expect(res.status).toBe(403)
})

test('POST creates announcement for ADMIN', async () => {
  ;(prisma.announcement.create as jest.Mock).mockResolvedValue({ id: 'a1', title: 'Hello' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Hello', content: 'World' }),
  })
  const res = await POST(req, adminCtx)
  expect(res.status).toBe(201)
})

test('POST returns 400 for missing title', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: '', content: 'World' }),
  })
  const res = await POST(req, adminCtx)
  expect(res.status).toBe(400)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/announcements.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement announcements route**

Create `app/api/announcements/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const WRITE_ROLES = new Set(['OWNER', 'ADMIN', 'MODERATOR'])

const createSchema = z.object({
  title: z.string().min(1, 'Title required').max(200),
  content: z.string().min(1, 'Content required'),
  isPinned: z.boolean().optional().default(false),
})

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const announcements = await prisma.announcement.findMany({
    where: { communityId: ctx.communityId },
    orderBy: [{ isPinned: 'desc' }, { publishedAt: 'desc' }],
    take: 50,
  })
  return NextResponse.json({ announcements })
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  if (!WRITE_ROLES.has(ctx.member.role)) {
    return NextResponse.json({ error: 'Forbidden' }, { status: 403 })
  }

  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const announcement = await prisma.announcement.create({
    data: {
      communityId: ctx.communityId,
      authorId: ctx.userId,
      title: parsed.data.title,
      content: parsed.data.content,
      isPinned: parsed.data.isPinned,
    },
  })

  return NextResponse.json({ announcement }, { status: 201 })
})
```

- [ ] **Step 4: Create announcements list page**

Create `app/(app)/announcements/page.tsx`:

```typescript
import Link from 'next/link'
import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function AnnouncementsPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')

  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const member = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!member) redirect('/onboarding')

  const canWrite = ['OWNER', 'ADMIN', 'MODERATOR'].includes(member.role)

  const announcements = await prisma.announcement.findMany({
    where: { communityId },
    orderBy: [{ isPinned: 'desc' }, { publishedAt: 'desc' }],
    take: 50,
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Announcements</h1>
        {canWrite && (
          <Link href="/announcements/new" className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium hover:opacity-90 transition-opacity">
            New Announcement
          </Link>
        )}
      </div>

      {announcements.length === 0 && (
        <p className="text-sm text-text-muted">No announcements yet.</p>
      )}

      <div className="flex flex-col gap-3">
        {announcements.map(a => (
          <div key={a.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-start justify-between gap-3">
              <div>
                {a.isPinned && (
                  <span className="text-[10px] uppercase tracking-wider text-text-muted border border-border-default rounded px-1.5 py-0.5 mr-2">Pinned</span>
                )}
                <span className="text-sm font-medium text-text-primary">{a.title}</span>
              </div>
              <span className="text-xs text-text-muted flex-shrink-0">
                {new Date(a.publishedAt).toLocaleDateString()}
              </span>
            </div>
            <p className="text-sm text-text-muted mt-2 line-clamp-3">{a.content}</p>
          </div>
        ))}
      </div>
    </div>
  )
}
```

- [ ] **Step 5: Create new announcement page**

Create `app/(app)/announcements/new/page.tsx`:

```typescript
'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

export default function NewAnnouncementPage() {
  const router = useRouter()
  const [title, setTitle] = useState('')
  const [content, setContent] = useState('')
  const [isPinned, setIsPinned] = useState(false)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError('')
    const res = await fetch('/api/announcements', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, content, isPinned }),
    })
    const json = await res.json()
    if (!res.ok) { setError(json.error); setLoading(false); return }
    router.push('/announcements')
  }

  return (
    <div className="max-w-2xl">
      <h1 className="text-xl font-semibold tracking-tight mb-6">New Announcement</h1>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input label="Title" id="title" value={title} onChange={e => setTitle(e.target.value)} />
        <div>
          <label htmlFor="content" className="block text-xs text-text-muted mb-1.5">Content</label>
          <textarea
            id="content"
            value={content}
            onChange={e => setContent(e.target.value)}
            rows={6}
            className="w-full bg-bg-elevated border border-border-default rounded px-3 py-2 text-sm text-text-primary placeholder:text-text-faint focus:outline-none focus:border-border-light resize-none"
          />
        </div>
        <label className="flex items-center gap-2 text-sm text-text-muted cursor-pointer">
          <input type="checkbox" checked={isPinned} onChange={e => setIsPinned(e.target.checked)} className="accent-white" />
          Pin this announcement
        </label>
        {error && <p className="text-xs text-danger">{error}</p>}
        <div className="flex gap-2">
          <Button type="submit" loading={loading}>Post Announcement</Button>
          <Button type="button" variant="ghost" onClick={() => router.back()}>Cancel</Button>
        </div>
      </form>
    </div>
  )
}
```

- [ ] **Step 6: Run tests — expect PASS**

```bash
npx jest __tests__/api/announcements.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 7: Commit**

```bash
git add app/api/announcements/route.ts app/'(app)'/announcements/ __tests__/api/announcements.test.ts
git commit -m "feat: add announcements API and pages"
```

---

## Task 6: Events

**Files:**
- Create: `app/api/events/route.ts`
- Create: `app/(app)/events/page.tsx`
- Create: `app/(app)/events/new/page.tsx`
- Test: `__tests__/api/events.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/api/events.test.ts`:

```typescript
import { GET, POST } from '@/app/api/events/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { event: { findMany: jest.fn(), create: jest.fn() } },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1' } as any, member: { role: 'ADMIN' } as any,
}
const memberCtx: CommunityContext = { ...adminCtx, member: { role: 'MEMBER' } as any }

beforeEach(() => jest.clearAllMocks())

test('GET returns events list', async () => {
  ;(prisma.event.findMany as jest.Mock).mockResolvedValue([{ id: 'e1', title: 'Patrol Night' }])
  const res = await GET(new Request('http://localhost'), adminCtx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.events).toHaveLength(1)
})

test('POST returns 403 for MEMBER role', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Event', startAt: new Date().toISOString() }),
  })
  expect((await POST(req, memberCtx)).status).toBe(403)
})

test('POST creates event for ADMIN', async () => {
  ;(prisma.event.create as jest.Mock).mockResolvedValue({ id: 'e1', title: 'Event' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: 'Event', startAt: new Date().toISOString() }),
  })
  expect((await POST(req, adminCtx)).status).toBe(201)
})

test('POST returns 400 for missing title', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title: '', startAt: new Date().toISOString() }),
  })
  expect((await POST(req, adminCtx)).status).toBe(400)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/events.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement events route**

Create `app/api/events/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const WRITE_ROLES = new Set(['OWNER', 'ADMIN', 'MODERATOR'])

const createSchema = z.object({
  title: z.string().min(1, 'Title required').max(200),
  description: z.string().optional(),
  startAt: z.string().datetime(),
  endAt: z.string().datetime().optional(),
  location: z.string().optional(),
})

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const events = await prisma.event.findMany({
    where: { communityId: ctx.communityId },
    orderBy: { startAt: 'asc' },
    take: 50,
  })
  return NextResponse.json({ events })
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  if (!WRITE_ROLES.has(ctx.member.role)) {
    return NextResponse.json({ error: 'Forbidden' }, { status: 403 })
  }

  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const event = await prisma.event.create({
    data: {
      communityId: ctx.communityId,
      authorId: ctx.userId,
      title: parsed.data.title,
      description: parsed.data.description,
      startAt: new Date(parsed.data.startAt),
      endAt: parsed.data.endAt ? new Date(parsed.data.endAt) : undefined,
      location: parsed.data.location,
      rsvps: {},
    },
  })

  return NextResponse.json({ event }, { status: 201 })
})
```

- [ ] **Step 4: Create events list page**

Create `app/(app)/events/page.tsx`:

```typescript
import Link from 'next/link'
import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function EventsPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const member = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!member) redirect('/onboarding')

  const canWrite = ['OWNER', 'ADMIN', 'MODERATOR'].includes(member.role)
  const events = await prisma.event.findMany({
    where: { communityId },
    orderBy: { startAt: 'asc' },
    take: 50,
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Events</h1>
        {canWrite && (
          <Link href="/events/new" className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium hover:opacity-90 transition-opacity">
            New Event
          </Link>
        )}
      </div>

      {events.length === 0 && <p className="text-sm text-text-muted">No events scheduled.</p>}

      <div className="flex flex-col gap-3">
        {events.map(ev => (
          <div key={ev.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-start justify-between gap-3">
              <span className="text-sm font-medium text-text-primary">{ev.title}</span>
              <span className="text-xs text-text-muted flex-shrink-0">
                {new Date(ev.startAt).toLocaleString()}
              </span>
            </div>
            {ev.description && <p className="text-sm text-text-muted mt-1.5 line-clamp-2">{ev.description}</p>}
            {ev.location && <p className="text-xs text-text-muted mt-1">📍 {ev.location}</p>}
          </div>
        ))}
      </div>
    </div>
  )
}
```

- [ ] **Step 5: Create new event page**

Create `app/(app)/events/new/page.tsx`:

```typescript
'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

export default function NewEventPage() {
  const router = useRouter()
  const [title, setTitle] = useState('')
  const [description, setDescription] = useState('')
  const [startAt, setStartAt] = useState('')
  const [endAt, setEndAt] = useState('')
  const [location, setLocation] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError('')
    const res = await fetch('/api/events', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ title, description, startAt, endAt: endAt || undefined, location: location || undefined }),
    })
    const json = await res.json()
    if (!res.ok) { setError(json.error); setLoading(false); return }
    router.push('/events')
  }

  return (
    <div className="max-w-2xl">
      <h1 className="text-xl font-semibold tracking-tight mb-6">New Event</h1>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input label="Title" id="title" value={title} onChange={e => setTitle(e.target.value)} />
        <Input label="Start" id="startAt" type="datetime-local" value={startAt} onChange={e => setStartAt(e.target.value)} />
        <Input label="End (optional)" id="endAt" type="datetime-local" value={endAt} onChange={e => setEndAt(e.target.value)} />
        <Input label="Location (optional)" id="location" value={location} onChange={e => setLocation(e.target.value)} />
        <div>
          <label htmlFor="description" className="block text-xs text-text-muted mb-1.5">Description (optional)</label>
          <textarea
            id="description"
            value={description}
            onChange={e => setDescription(e.target.value)}
            rows={4}
            className="w-full bg-bg-elevated border border-border-default rounded px-3 py-2 text-sm text-text-primary placeholder:text-text-faint focus:outline-none focus:border-border-light resize-none"
          />
        </div>
        {error && <p className="text-xs text-danger">{error}</p>}
        <div className="flex gap-2">
          <Button type="submit" loading={loading}>Create Event</Button>
          <Button type="button" variant="ghost" onClick={() => router.back()}>Cancel</Button>
        </div>
      </form>
    </div>
  )
}
```

- [ ] **Step 6: Run tests — expect PASS**

```bash
npx jest __tests__/api/events.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 7: Commit**

```bash
git add app/api/events/route.ts app/'(app)'/events/ __tests__/api/events.test.ts
git commit -m "feat: add events API and pages"
```

---

## Task 7: Roster

**Files:**
- Create: `app/api/roster/route.ts`
- Create: `app/(app)/roster/page.tsx`
- Test: `__tests__/api/roster.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/api/roster.test.ts`:

```typescript
import { GET } from '@/app/api/roster/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { communityMember: { findMany: jest.fn(), count: jest.fn() } },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const ctx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1' } as any, member: { role: 'MEMBER' } as any,
}

const fakeMembers = [
  { id: 'm1', role: 'ADMIN', status: 'ACTIVE', callsign: 'A-01',
    user: { email: 'admin@test.com', avatar: null },
    department: { name: 'Police' }, rank: { name: 'Officer' } },
]

beforeEach(() => jest.clearAllMocks())

test('GET returns members list', async () => {
  ;(prisma.communityMember.findMany as jest.Mock).mockResolvedValue(fakeMembers)
  ;(prisma.communityMember.count as jest.Mock).mockResolvedValue(1)
  const res = await GET(new Request('http://localhost?page=1'), ctx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.members).toHaveLength(1)
  expect(json.total).toBe(1)
})

test('GET filters by department', async () => {
  ;(prisma.communityMember.findMany as jest.Mock).mockResolvedValue([])
  ;(prisma.communityMember.count as jest.Mock).mockResolvedValue(0)
  const res = await GET(new Request('http://localhost?departmentId=d1'), ctx)
  expect(res.status).toBe(200)
  expect(prisma.communityMember.findMany).toHaveBeenCalledWith(
    expect.objectContaining({ where: expect.objectContaining({ departmentId: 'd1' }) })
  )
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/roster.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement roster route**

Create `app/api/roster/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

export const GET = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const url = new URL(req.url)
  const page = Math.max(1, parseInt(url.searchParams.get('page') ?? '1'))
  const departmentId = url.searchParams.get('departmentId') ?? undefined
  const status = url.searchParams.get('status') ?? 'ACTIVE'
  const PAGE_SIZE = 50

  const where = {
    communityId: ctx.communityId,
    status,
    ...(departmentId ? { departmentId } : {}),
  }

  const [members, total] = await Promise.all([
    prisma.communityMember.findMany({
      where,
      include: {
        user: { select: { email: true, avatar: true } },
        department: { select: { name: true } },
        rank: { select: { name: true } },
      },
      orderBy: [{ role: 'asc' }, { joinedAt: 'asc' }],
      skip: (page - 1) * PAGE_SIZE,
      take: PAGE_SIZE,
    }),
    prisma.communityMember.count({ where }),
  ])

  return NextResponse.json({ members, total, page, pageSize: PAGE_SIZE })
})
```

- [ ] **Step 4: Create roster page**

Create `app/(app)/roster/page.tsx`:

```typescript
import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function RosterPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const [members, departments] = await Promise.all([
    prisma.communityMember.findMany({
      where: { communityId, status: 'ACTIVE' },
      include: {
        user: { select: { email: true, avatar: true } },
        department: { select: { name: true } },
        rank: { select: { name: true } },
      },
      orderBy: [{ role: 'asc' }, { joinedAt: 'asc' }],
      take: 100,
    }),
    prisma.department.findMany({
      where: { communityId },
      orderBy: { sortOrder: 'asc' },
    }),
  ])

  const roleOrder = ['OWNER', 'ADMIN', 'MODERATOR', 'MEMBER']

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Roster</h1>
        <span className="text-xs text-text-muted">{members.length} members</span>
      </div>

      <div className="bg-bg-surface border border-border-default rounded-lg overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border-default">
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Member</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Department</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Rank</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Role</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Callsign</th>
            </tr>
          </thead>
          <tbody>
            {members.map(m => (
              <tr key={m.id} className="border-b border-border-default last:border-0 hover:bg-bg-elevated transition-colors">
                <td className="px-4 py-2.5">
                  <div className="flex items-center gap-2">
                    <div className="w-6 h-6 rounded-full bg-bg-elevated flex items-center justify-center text-[10px] text-text-muted flex-shrink-0">
                      {m.user.email[0]?.toUpperCase()}
                    </div>
                    <span className="text-text-secondary truncate max-w-[160px]">{m.user.email}</span>
                  </div>
                </td>
                <td className="px-4 py-2.5 text-text-muted">{m.department?.name ?? '—'}</td>
                <td className="px-4 py-2.5 text-text-muted">{m.rank?.name ?? '—'}</td>
                <td className="px-4 py-2.5 text-text-muted capitalize">{m.role.toLowerCase()}</td>
                <td className="px-4 py-2.5 text-text-muted">{m.callsign ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
npx jest __tests__/api/roster.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 6: Commit**

```bash
git add app/api/roster/route.ts app/'(app)'/roster/page.tsx __tests__/api/roster.test.ts
git commit -m "feat: add roster API and page"
```

---

## Task 8: Messages

**Files:**
- Create: `app/api/messages/route.ts`
- Create: `app/(app)/messages/page.tsx`
- Test: `__tests__/api/messages.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/api/messages.test.ts`:

```typescript
import { GET, POST } from '@/app/api/messages/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { message: { findMany: jest.fn(), create: jest.fn() } },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const ctx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1' } as any, member: { role: 'MEMBER' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('GET returns inbox messages', async () => {
  ;(prisma.message.findMany as jest.Mock).mockResolvedValue([
    { id: 'msg1', content: 'Hello', senderId: 'u2', recipientId: 'u1' },
  ])
  const res = await GET(new Request('http://localhost'), ctx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.messages).toHaveLength(1)
})

test('POST sends a message', async () => {
  ;(prisma.message.create as jest.Mock).mockResolvedValue({ id: 'msg1' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ recipientId: 'u2', content: 'Hello!' }),
  })
  const res = await POST(req, ctx)
  expect(res.status).toBe(201)
})

test('POST returns 400 if recipientId missing', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ content: 'Hello!' }),
  })
  expect((await POST(req, ctx)).status).toBe(400)
})

test('POST returns 400 if sending to self', async () => {
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ recipientId: 'u1', content: 'Hello!' }),
  })
  expect((await POST(req, ctx)).status).toBe(400)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/messages.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement messages route**

Create `app/api/messages/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const sendSchema = z.object({
  recipientId: z.string().min(1, 'recipientId required'),
  content: z.string().min(1, 'Content required').max(2000),
})

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const messages = await prisma.message.findMany({
    where: { communityId: ctx.communityId, recipientId: ctx.userId },
    orderBy: { createdAt: 'desc' },
    take: 50,
  })
  return NextResponse.json({ messages })
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const body = await req.json()
  const parsed = sendSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  if (parsed.data.recipientId === ctx.userId) {
    return NextResponse.json({ error: 'Cannot message yourself' }, { status: 400 })
  }

  const message = await prisma.message.create({
    data: {
      communityId: ctx.communityId,
      senderId: ctx.userId,
      recipientId: parsed.data.recipientId,
      content: parsed.data.content,
    },
  })

  return NextResponse.json({ message }, { status: 201 })
})
```

- [ ] **Step 4: Create messages page**

Create `app/(app)/messages/page.tsx`:

```typescript
'use client'
import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

interface Message {
  id: string
  senderId: string
  content: string
  createdAt: string
  readAt: string | null
}

export default function MessagesPage() {
  const [messages, setMessages] = useState<Message[]>([])
  const [loading, setLoading] = useState(true)
  const [composing, setComposing] = useState(false)
  const [recipientId, setRecipientId] = useState('')
  const [content, setContent] = useState('')
  const [sending, setSending] = useState(false)
  const [error, setError] = useState('')

  useEffect(() => {
    fetch('/api/messages')
      .then(r => r.json())
      .then(j => { setMessages(j.messages ?? []); setLoading(false) })
      .catch(() => setLoading(false))
  }, [])

  async function sendMessage(e: React.FormEvent) {
    e.preventDefault()
    setSending(true)
    setError('')
    const res = await fetch('/api/messages', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ recipientId, content }),
    })
    const json = await res.json()
    if (!res.ok) { setError(json.error); setSending(false); return }
    setComposing(false)
    setRecipientId('')
    setContent('')
    setSending(false)
  }

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Messages</h1>
        <Button size="sm" onClick={() => setComposing(c => !c)}>
          {composing ? 'Cancel' : 'Compose'}
        </Button>
      </div>

      {composing && (
        <form onSubmit={sendMessage} className="bg-bg-surface border border-border-default rounded-lg p-4 mb-4 flex flex-col gap-3">
          <Input label="Recipient ID" id="recipient" value={recipientId} onChange={e => setRecipientId(e.target.value)} />
          <div>
            <label htmlFor="content" className="block text-xs text-text-muted mb-1.5">Message</label>
            <textarea
              id="content"
              value={content}
              onChange={e => setContent(e.target.value)}
              rows={3}
              className="w-full bg-bg-elevated border border-border-default rounded px-3 py-2 text-sm text-text-primary focus:outline-none focus:border-border-light resize-none"
            />
          </div>
          {error && <p className="text-xs text-danger">{error}</p>}
          <Button type="submit" loading={sending} size="sm">Send</Button>
        </form>
      )}

      {loading && <p className="text-sm text-text-muted">Loading...</p>}
      {!loading && messages.length === 0 && <p className="text-sm text-text-muted">No messages.</p>}

      <div className="flex flex-col gap-2">
        {messages.map(m => (
          <div key={m.id} className={`bg-bg-surface border rounded-lg p-3 ${m.readAt ? 'border-border-default' : 'border-border-light'}`}>
            <div className="flex items-center justify-between mb-1">
              <span className="text-xs text-text-muted">From: {m.senderId}</span>
              <span className="text-xs text-text-muted">{new Date(m.createdAt).toLocaleString()}</span>
            </div>
            <p className="text-sm text-text-secondary">{m.content}</p>
          </div>
        ))}
      </div>
    </div>
  )
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
npx jest __tests__/api/messages.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 6: Commit**

```bash
git add app/api/messages/route.ts app/'(app)'/messages/page.tsx __tests__/api/messages.test.ts
git commit -m "feat: add messages API and page"
```

---

## Task 9: Admin — Applications

**Files:**
- Create: `app/api/admin/applications/route.ts`
- Create: `app/api/admin/applications/[id]/route.ts`
- Create: `app/(app)/admin/applications/page.tsx`
- Test: `__tests__/api/admin-applications.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/api/admin-applications.test.ts`:

```typescript
import { GET } from '@/app/api/admin/applications/route'
import { PATCH } from '@/app/api/admin/applications/[id]/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler, opts) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: { application: { findMany: jest.fn(), update: jest.fn(), findFirst: jest.fn() } },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1' } as any, member: { role: 'ADMIN' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('GET returns pending applications', async () => {
  ;(prisma.application.findMany as jest.Mock).mockResolvedValue([
    { id: 'app1', status: 'PENDING', applicantUserId: 'u2', formData: {} },
  ])
  const res = await GET(new Request('http://localhost'), adminCtx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.applications).toHaveLength(1)
})

test('PATCH approves an application', async () => {
  ;(prisma.application.findFirst as jest.Mock).mockResolvedValue({ id: 'app1', communityId: 'c1', status: 'PENDING' })
  ;(prisma.application.update as jest.Mock).mockResolvedValue({ id: 'app1', status: 'APPROVED' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'approve' }),
  })
  const res = await PATCH(req, adminCtx, { params: Promise.resolve({ id: 'app1' }) })
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.application.status).toBe('APPROVED')
})

test('PATCH denies an application with notes', async () => {
  ;(prisma.application.findFirst as jest.Mock).mockResolvedValue({ id: 'app1', communityId: 'c1', status: 'PENDING' })
  ;(prisma.application.update as jest.Mock).mockResolvedValue({ id: 'app1', status: 'DENIED' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'deny', notes: 'Not eligible' }),
  })
  const res = await PATCH(req, adminCtx, { params: Promise.resolve({ id: 'app1' }) })
  expect(res.status).toBe(200)
})

test('PATCH returns 404 if application not found', async () => {
  ;(prisma.application.findFirst as jest.Mock).mockResolvedValue(null)
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'approve' }),
  })
  const res = await PATCH(req, adminCtx, { params: Promise.resolve({ id: 'missing' }) })
  expect(res.status).toBe(404)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/admin-applications.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement applications list route**

Create `app/api/admin/applications/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

export const GET = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const url = new URL(req.url)
  const status = url.searchParams.get('status') ?? 'PENDING'

  const applications = await prisma.application.findMany({
    where: { communityId: ctx.communityId, status },
    orderBy: { createdAt: 'desc' },
    take: 50,
  })

  return NextResponse.json({ applications })
}, { adminOnly: true })
```

- [ ] **Step 4: Implement application review route**

Create `app/api/admin/applications/[id]/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const reviewSchema = z.object({
  action: z.enum(['approve', 'deny']),
  notes: z.string().optional(),
})

export async function PATCH(
  req: Request,
  ctx: CommunityContext,
  { params }: { params: Promise<{ id: string }> }
) {
  return withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
    const { id } = await params
    const body = await req.json()
    const parsed = reviewSchema.safeParse(body)
    if (!parsed.success) {
      return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
    }

    const application = await prisma.application.findFirst({
      where: { id, communityId: ctx.communityId },
    })
    if (!application) return NextResponse.json({ error: 'Not found' }, { status: 404 })

    const status = parsed.data.action === 'approve' ? 'APPROVED' : 'DENIED'
    const updated = await prisma.application.update({
      where: { id },
      data: { status, reviewedBy: ctx.userId, reviewedAt: new Date(), notes: parsed.data.notes },
    })

    return NextResponse.json({ application: updated })
  }, { adminOnly: true })(req, ctx)
}
```

**Note:** The route handler above wraps the inner logic in `withCommunityAuth` manually. A cleaner pattern for dynamic routes is to export the raw handler and call `withCommunityAuth` at the export level. Update to this pattern:

Replace the file with:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const reviewSchema = z.object({
  action: z.enum(['approve', 'deny']),
  notes: z.string().optional(),
})

async function patchHandler(
  req: Request,
  ctx: CommunityContext,
  id: string
): Promise<Response> {
  const body = await req.json()
  const parsed = reviewSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const application = await prisma.application.findFirst({
    where: { id, communityId: ctx.communityId },
  })
  if (!application) return NextResponse.json({ error: 'Not found' }, { status: 404 })

  const status = parsed.data.action === 'approve' ? 'APPROVED' : 'DENIED'
  const updated = await prisma.application.update({
    where: { id },
    data: { status, reviewedBy: ctx.userId, reviewedAt: new Date(), notes: parsed.data.notes },
  })

  return NextResponse.json({ application: updated })
}

export async function PATCH(
  req: Request,
  ctx: CommunityContext,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params
  return withCommunityAuth((r, c) => patchHandler(r, c, id), { adminOnly: true })(req)
}
```

- [ ] **Step 5: Create applications admin page**

Create `app/(app)/admin/applications/page.tsx`:

```typescript
import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function AdminApplicationsPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const member = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!member || !['OWNER', 'ADMIN'].includes(member.role)) redirect('/dashboard')

  const applications = await prisma.application.findMany({
    where: { communityId, status: 'PENDING' },
    orderBy: { createdAt: 'asc' },
  })

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight mb-6">Applications</h1>

      {applications.length === 0 && (
        <p className="text-sm text-text-muted">No pending applications.</p>
      )}

      <div className="flex flex-col gap-3">
        {applications.map(app => (
          <div key={app.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm text-text-secondary">{app.applicantUserId}</p>
                <p className="text-xs text-text-muted mt-0.5">
                  Applied {new Date(app.createdAt).toLocaleDateString()}
                </p>
              </div>
              <div className="flex gap-2">
                <form action={`/api/admin/applications/${app.id}`} method="post">
                  <input type="hidden" name="action" value="approve" />
                  <button type="submit" className="text-xs bg-white text-black px-2.5 py-1 rounded font-medium">
                    Approve
                  </button>
                </form>
                <form action={`/api/admin/applications/${app.id}`} method="post">
                  <input type="hidden" name="action" value="deny" />
                  <button type="submit" className="text-xs border border-border-default text-text-muted px-2.5 py-1 rounded">
                    Deny
                  </button>
                </form>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
```

**Note for implementer:** The approve/deny buttons in the page above use HTML form `action` pointing to the API routes — this won't work directly with `PATCH` methods. Replace the button section with a client component `ApplicationActions` that calls `fetch('/api/admin/applications/${app.id}', { method: 'PATCH', body: JSON.stringify({ action }) })` and triggers a page refresh via `router.refresh()`. Create `app/(app)/admin/applications/ApplicationActions.tsx`:

```typescript
'use client'
import { useState } from 'react'
import { useRouter } from 'next/navigation'

export function ApplicationActions({ applicationId }: { applicationId: string }) {
  const router = useRouter()
  const [loading, setLoading] = useState<'approve' | 'deny' | null>(null)

  async function review(action: 'approve' | 'deny') {
    setLoading(action)
    await fetch(`/api/admin/applications/${applicationId}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action }),
    })
    router.refresh()
  }

  return (
    <div className="flex gap-2">
      <button
        onClick={() => review('approve')}
        disabled={loading !== null}
        className="text-xs bg-white text-black px-2.5 py-1 rounded font-medium disabled:opacity-50"
      >
        {loading === 'approve' ? '...' : 'Approve'}
      </button>
      <button
        onClick={() => review('deny')}
        disabled={loading !== null}
        className="text-xs border border-border-default text-text-muted px-2.5 py-1 rounded disabled:opacity-50"
      >
        {loading === 'deny' ? '...' : 'Deny'}
      </button>
    </div>
  )
}
```

Then import `ApplicationActions` into the page and replace the form buttons.

- [ ] **Step 6: Run tests — expect PASS**

```bash
npx jest __tests__/api/admin-applications.test.ts --no-coverage 2>&1 | tail -20
```

Fix any failures, then:

- [ ] **Step 7: Commit**

```bash
git add app/api/admin/applications/ app/'(app)'/admin/applications/ __tests__/api/admin-applications.test.ts
git commit -m "feat: add admin applications review API and page"
```

---

## Task 10: Admin — Members

**Files:**
- Create: `app/api/admin/members/[id]/route.ts`
- Create: `app/(app)/admin/members/page.tsx`
- Test: `__tests__/api/admin-members.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/api/admin-members.test.ts`:

```typescript
import { PATCH, DELETE } from '@/app/api/admin/members/[id]/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler, opts) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    communityMember: {
      findFirst: jest.fn(),
      update: jest.fn(),
      delete: jest.fn(),
    },
  },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1' } as any, member: { role: 'ADMIN', id: 'own-member' } as any,
}

const fakeMember = { id: 'tm1', communityId: 'c1', userId: 'u2', role: 'MEMBER', status: 'ACTIVE' }

beforeEach(() => jest.clearAllMocks())

const params = { params: Promise.resolve({ id: 'tm1' }) }

test('PATCH updates member role', async () => {
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue(fakeMember)
  ;(prisma.communityMember.update as jest.Mock).mockResolvedValue({ ...fakeMember, role: 'MODERATOR' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ role: 'MODERATOR' }),
  })
  const res = await PATCH(req, adminCtx, params)
  expect(res.status).toBe(200)
})

test('PATCH returns 404 if member not found', async () => {
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue(null)
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ role: 'MODERATOR' }),
  })
  expect((await PATCH(req, adminCtx, params)).status).toBe(404)
})

test('PATCH returns 400 if trying to change OWNER role', async () => {
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue({ ...fakeMember, role: 'OWNER' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ role: 'MEMBER' }),
  })
  expect((await PATCH(req, adminCtx, params)).status).toBe(400)
})

test('DELETE removes member', async () => {
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue(fakeMember)
  ;(prisma.communityMember.delete as jest.Mock).mockResolvedValue(fakeMember)
  const res = await DELETE(new Request('http://localhost'), adminCtx, params)
  expect(res.status).toBe(200)
})

test('DELETE returns 400 when removing OWNER', async () => {
  ;(prisma.communityMember.findFirst as jest.Mock).mockResolvedValue({ ...fakeMember, role: 'OWNER' })
  expect((await DELETE(new Request('http://localhost'), adminCtx, params)).status).toBe(400)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/admin-members.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement admin members route**

Create `app/api/admin/members/[id]/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const updateSchema = z.object({
  role: z.enum(['ADMIN', 'MODERATOR', 'MEMBER']).optional(),
  status: z.enum(['ACTIVE', 'INACTIVE', 'SUSPENDED']).optional(),
  departmentId: z.string().nullable().optional(),
  rankId: z.string().nullable().optional(),
  callsign: z.string().nullable().optional(),
})

async function patchHandler(req: Request, ctx: CommunityContext, id: string): Promise<Response> {
  const target = await prisma.communityMember.findFirst({
    where: { id, communityId: ctx.communityId },
  })
  if (!target) return NextResponse.json({ error: 'Not found' }, { status: 404 })
  if (target.role === 'OWNER') return NextResponse.json({ error: 'Cannot modify owner' }, { status: 400 })

  const body = await req.json()
  const parsed = updateSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const updated = await prisma.communityMember.update({
    where: { id },
    data: parsed.data,
  })

  return NextResponse.json({ member: updated })
}

async function deleteHandler(req: Request, ctx: CommunityContext, id: string): Promise<Response> {
  const target = await prisma.communityMember.findFirst({
    where: { id, communityId: ctx.communityId },
  })
  if (!target) return NextResponse.json({ error: 'Not found' }, { status: 404 })
  if (target.role === 'OWNER') return NextResponse.json({ error: 'Cannot remove owner' }, { status: 400 })

  await prisma.communityMember.delete({ where: { id } })
  return NextResponse.json({ ok: true })
}

export async function PATCH(
  req: Request,
  ctx: CommunityContext,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params
  return withCommunityAuth((r, c) => patchHandler(r, c, id), { adminOnly: true })(req)
}

export async function DELETE(
  req: Request,
  ctx: CommunityContext,
  { params }: { params: Promise<{ id: string }> }
) {
  const { id } = await params
  return withCommunityAuth((r, c) => deleteHandler(r, c, id), { adminOnly: true })(req)
}
```

- [ ] **Step 4: Create admin members page**

Create `app/(app)/admin/members/page.tsx`:

```typescript
import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function AdminMembersPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const currentMember = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!currentMember || !['OWNER', 'ADMIN'].includes(currentMember.role)) redirect('/dashboard')

  const members = await prisma.communityMember.findMany({
    where: { communityId },
    include: {
      user: { select: { email: true } },
      department: { select: { name: true } },
      rank: { select: { name: true } },
    },
    orderBy: { joinedAt: 'asc' },
  })

  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight mb-6">Members</h1>
      <div className="bg-bg-surface border border-border-default rounded-lg overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border-default">
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Member</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Role</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Status</th>
              <th className="text-left text-[10px] uppercase tracking-wider text-text-muted px-4 py-2.5 font-normal">Department</th>
              <th className="px-4 py-2.5 font-normal"></th>
            </tr>
          </thead>
          <tbody>
            {members.map(m => (
              <tr key={m.id} className="border-b border-border-default last:border-0">
                <td className="px-4 py-2.5 text-text-secondary">{m.user.email}</td>
                <td className="px-4 py-2.5 text-text-muted capitalize">{m.role.toLowerCase()}</td>
                <td className="px-4 py-2.5 text-text-muted capitalize">{m.status.toLowerCase()}</td>
                <td className="px-4 py-2.5 text-text-muted">{m.department?.name ?? '—'}</td>
                <td className="px-4 py-2.5">
                  {m.role !== 'OWNER' && (
                    <span className="text-xs text-text-muted">
                      {/* client actions handled by MemberActions component */}
                      Edit
                    </span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
npx jest __tests__/api/admin-members.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 6: Commit**

```bash
git add app/api/admin/members/ app/'(app)'/admin/members/ __tests__/api/admin-members.test.ts
git commit -m "feat: add admin members management API and page"
```

---

## Task 11: Admin — Departments + Ranks

**Files:**
- Create: `app/api/admin/departments/route.ts`
- Create: `app/api/admin/departments/[id]/route.ts`
- Create: `app/api/admin/departments/[id]/ranks/route.ts`
- Create: `app/api/admin/ranks/[id]/route.ts`
- Create: `app/(app)/admin/departments/page.tsx`
- Test: `__tests__/api/admin-departments.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/api/admin-departments.test.ts`:

```typescript
import { GET, POST } from '@/app/api/admin/departments/route'
import { PATCH, DELETE } from '@/app/api/admin/departments/[id]/route'
import { POST as POST_RANK } from '@/app/api/admin/departments/[id]/ranks/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    department: { findMany: jest.fn(), create: jest.fn(), findFirst: jest.fn(), update: jest.fn(), delete: jest.fn() },
    rank: { create: jest.fn() },
    communityMember: { count: jest.fn() },
  },
}))
jest.mock('@/lib/plans', () => ({
  checkPlanLimit: jest.fn(),
  PLANS: { FREE: { limits: { departments: 1 } }, STANDARD: { limits: { departments: 5 } }, PRO: { limits: { departments: null } } },
}))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1', planTier: 'STANDARD' } as any,
  member: { role: 'ADMIN' } as any,
}

beforeEach(() => jest.clearAllMocks())

const deptParams = { params: Promise.resolve({ id: 'd1' }) }

test('GET returns departments with ranks', async () => {
  ;(prisma.department.findMany as jest.Mock).mockResolvedValue([{ id: 'd1', name: 'Police', ranks: [] }])
  const res = await GET(new Request('http://localhost'), adminCtx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.departments).toHaveLength(1)
})

test('POST creates department', async () => {
  ;(prisma.department.findMany as jest.Mock).mockResolvedValue([])
  ;(prisma.department.create as jest.Mock).mockResolvedValue({ id: 'd1', name: 'Fire' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Fire Department' }),
  })
  expect((await POST(req, adminCtx)).status).toBe(201)
})

test('PATCH updates department', async () => {
  ;(prisma.department.findFirst as jest.Mock).mockResolvedValue({ id: 'd1', communityId: 'c1' })
  ;(prisma.department.update as jest.Mock).mockResolvedValue({ id: 'd1', name: 'Updated' })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Updated' }),
  })
  expect((await PATCH(req, adminCtx, deptParams)).status).toBe(200)
})

test('DELETE returns 400 if department has members', async () => {
  ;(prisma.department.findFirst as jest.Mock).mockResolvedValue({ id: 'd1', communityId: 'c1' })
  ;(prisma.communityMember.count as jest.Mock).mockResolvedValue(3)
  expect((await DELETE(new Request('http://localhost'), adminCtx, deptParams)).status).toBe(400)
})

test('DELETE removes department with no members', async () => {
  ;(prisma.department.findFirst as jest.Mock).mockResolvedValue({ id: 'd1', communityId: 'c1' })
  ;(prisma.communityMember.count as jest.Mock).mockResolvedValue(0)
  ;(prisma.department.delete as jest.Mock).mockResolvedValue({ id: 'd1' })
  expect((await DELETE(new Request('http://localhost'), adminCtx, deptParams)).status).toBe(200)
})

test('POST rank creates rank under department', async () => {
  ;(prisma.department.findFirst as jest.Mock).mockResolvedValue({ id: 'd1', communityId: 'c1' })
  ;(prisma.rank.create as jest.Mock).mockResolvedValue({ id: 'r1', name: 'Officer' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Officer', level: 1 }),
  })
  expect((await POST_RANK(req, adminCtx, deptParams)).status).toBe(201)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/admin-departments.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement departments routes**

Create `app/api/admin/departments/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import { checkPlanLimit } from '@/lib/plans'
import { PlanLimitError } from '@/lib/plans'
import type { CommunityContext } from '@/lib/community-auth'

const createSchema = z.object({
  name: z.string().min(1, 'Name required').max(80),
  description: z.string().optional(),
  color: z.string().optional(),
  sortOrder: z.number().optional().default(0),
})

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const departments = await prisma.department.findMany({
    where: { communityId: ctx.communityId },
    include: { ranks: { orderBy: { level: 'asc' } } },
    orderBy: { sortOrder: 'asc' },
  })
  return NextResponse.json({ departments })
}, { adminOnly: true })

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const currentCount = await prisma.department.findMany({
    where: { communityId: ctx.communityId },
  }).then(r => r.length)

  try {
    checkPlanLimit(ctx.community.planTier, 'departments', currentCount)
  } catch (e) {
    if (e instanceof PlanLimitError) {
      return NextResponse.json({ error: e.message }, { status: 403 })
    }
    throw e
  }

  const department = await prisma.department.create({
    data: { communityId: ctx.communityId, ...parsed.data },
  })

  return NextResponse.json({ department }, { status: 201 })
}, { adminOnly: true })
```

Create `app/api/admin/departments/[id]/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const updateSchema = z.object({
  name: z.string().min(1).max(80).optional(),
  description: z.string().optional(),
  color: z.string().optional(),
  sortOrder: z.number().optional(),
})

async function patchHandler(req: Request, ctx: CommunityContext, id: string) {
  const dept = await prisma.department.findFirst({ where: { id, communityId: ctx.communityId } })
  if (!dept) return NextResponse.json({ error: 'Not found' }, { status: 404 })

  const body = await req.json()
  const parsed = updateSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })

  const updated = await prisma.department.update({ where: { id }, data: parsed.data })
  return NextResponse.json({ department: updated })
}

async function deleteHandler(req: Request, ctx: CommunityContext, id: string) {
  const dept = await prisma.department.findFirst({ where: { id, communityId: ctx.communityId } })
  if (!dept) return NextResponse.json({ error: 'Not found' }, { status: 404 })

  const memberCount = await prisma.communityMember.count({ where: { departmentId: id } })
  if (memberCount > 0) {
    return NextResponse.json({ error: 'Cannot delete department with members' }, { status: 400 })
  }

  await prisma.department.delete({ where: { id } })
  return NextResponse.json({ ok: true })
}

export async function PATCH(req: Request, ctx: CommunityContext, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return withCommunityAuth((r, c) => patchHandler(r, c, id), { adminOnly: true })(req)
}

export async function DELETE(req: Request, ctx: CommunityContext, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return withCommunityAuth((r, c) => deleteHandler(r, c, id), { adminOnly: true })(req)
}
```

Create `app/api/admin/departments/[id]/ranks/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const createSchema = z.object({
  name: z.string().min(1, 'Name required').max(80),
  level: z.number().int().min(0).default(0),
  isCommand: z.boolean().optional().default(false),
})

async function postHandler(req: Request, ctx: CommunityContext, departmentId: string) {
  const dept = await prisma.department.findFirst({ where: { id: departmentId, communityId: ctx.communityId } })
  if (!dept) return NextResponse.json({ error: 'Not found' }, { status: 404 })

  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })

  const rank = await prisma.rank.create({
    data: { communityId: ctx.communityId, departmentId, ...parsed.data },
  })
  return NextResponse.json({ rank }, { status: 201 })
}

export async function POST(req: Request, ctx: CommunityContext, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return withCommunityAuth((r, c) => postHandler(r, c, id), { adminOnly: true })(req)
}
```

Create `app/api/admin/ranks/[id]/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const updateSchema = z.object({
  name: z.string().min(1).max(80).optional(),
  level: z.number().int().min(0).optional(),
  isCommand: z.boolean().optional(),
})

async function patchHandler(req: Request, ctx: CommunityContext, id: string) {
  const rank = await prisma.rank.findFirst({ where: { id, communityId: ctx.communityId } })
  if (!rank) return NextResponse.json({ error: 'Not found' }, { status: 404 })
  const body = await req.json()
  const parsed = updateSchema.safeParse(body)
  if (!parsed.success) return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  const updated = await prisma.rank.update({ where: { id }, data: parsed.data })
  return NextResponse.json({ rank: updated })
}

async function deleteHandler(_req: Request, ctx: CommunityContext, id: string) {
  const rank = await prisma.rank.findFirst({ where: { id, communityId: ctx.communityId } })
  if (!rank) return NextResponse.json({ error: 'Not found' }, { status: 404 })
  await prisma.rank.delete({ where: { id } })
  return NextResponse.json({ ok: true })
}

export async function PATCH(req: Request, ctx: CommunityContext, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return withCommunityAuth((r, c) => patchHandler(r, c, id), { adminOnly: true })(req)
}

export async function DELETE(req: Request, ctx: CommunityContext, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params
  return withCommunityAuth((r, c) => deleteHandler(r, c, id), { adminOnly: true })(req)
}
```

- [ ] **Step 4: Create departments admin page**

Create `app/(app)/admin/departments/page.tsx`:

```typescript
import { cookies } from 'next/headers'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { redirect } from 'next/navigation'

export default async function AdminDepartmentsPage() {
  const session = await auth()
  if (!session?.user?.id) redirect('/login')
  const cookieStore = await cookies()
  const communityId = cookieStore.get('active_community_id')?.value
  if (!communityId) redirect('/onboarding')

  const currentMember = await prisma.communityMember.findFirst({
    where: { communityId, userId: session.user.id, status: 'ACTIVE' },
  })
  if (!currentMember || !['OWNER', 'ADMIN'].includes(currentMember.role)) redirect('/dashboard')

  const departments = await prisma.department.findMany({
    where: { communityId },
    include: { ranks: { orderBy: { level: 'asc' } }, _count: { select: { members: true } } },
    orderBy: { sortOrder: 'asc' },
  })

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <h1 className="text-xl font-semibold tracking-tight">Departments</h1>
        <button className="text-xs bg-white text-black px-3 py-1.5 rounded font-medium">
          Add Department
        </button>
      </div>

      {departments.length === 0 && <p className="text-sm text-text-muted">No departments yet.</p>}

      <div className="flex flex-col gap-3">
        {departments.map(dept => (
          <div key={dept.id} className="bg-bg-surface border border-border-default rounded-lg p-4">
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm font-medium">{dept.name}</span>
              <span className="text-xs text-text-muted">{dept._count.members} members</span>
            </div>
            {dept.ranks.length > 0 && (
              <div className="flex flex-wrap gap-1 mt-2">
                {dept.ranks.map(r => (
                  <span key={r.id} className="text-[10px] border border-border-default text-text-muted rounded px-1.5 py-0.5">
                    {r.name}
                  </span>
                ))}
              </div>
            )}
          </div>
        ))}
      </div>
    </div>
  )
}
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
npx jest __tests__/api/admin-departments.test.ts --no-coverage 2>&1 | tail -20
```

- [ ] **Step 6: Commit**

```bash
git add app/api/admin/departments/ app/api/admin/ranks/ app/'(app)'/admin/departments/ __tests__/api/admin-departments.test.ts
git commit -m "feat: add admin departments and ranks API and page"
```

---

## Task 12: Admin — Settings + Invite Links

**Files:**
- Create: `app/api/admin/settings/route.ts`
- Create: `app/api/admin/invites/route.ts`
- Create: `app/(app)/admin/settings/page.tsx`
- Test: `__tests__/api/admin-settings.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/api/admin-settings.test.ts`:

```typescript
import { GET, PATCH } from '@/app/api/admin/settings/route'
import { POST as POST_INVITE } from '@/app/api/admin/invites/route'

jest.mock('@/lib/community-auth', () => ({ withCommunityAuth: jest.fn((handler) => handler) }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    community: { findUnique: jest.fn(), update: jest.fn() },
    inviteLink: { create: jest.fn() },
  },
}))
jest.mock('nanoid', () => ({ nanoid: jest.fn(() => 'abc123xyz') }))

import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const adminCtx: CommunityContext = {
  userId: 'u1', communityId: 'c1',
  community: { id: 'c1', name: 'Test', isPublic: false } as any,
  member: { role: 'ADMIN' } as any,
}

beforeEach(() => jest.clearAllMocks())

test('GET returns community settings', async () => {
  ;(prisma.community.findUnique as jest.Mock).mockResolvedValue({
    id: 'c1', name: 'Test', isPublic: false, autoApproveMembers: false, discordServerId: null,
  })
  const res = await GET(new Request('http://localhost'), adminCtx)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.settings.name).toBe('Test')
})

test('PATCH updates community settings', async () => {
  ;(prisma.community.update as jest.Mock).mockResolvedValue({ id: 'c1', name: 'New Name', isPublic: true })
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'New Name', isPublic: true }),
  })
  const res = await PATCH(req, adminCtx)
  expect(res.status).toBe(200)
})

test('PATCH returns 400 for invalid name', async () => {
  const req = new Request('http://localhost', {
    method: 'PATCH', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: '' }),
  })
  expect((await PATCH(req, adminCtx)).status).toBe(400)
})

test('POST invite creates invite link', async () => {
  ;(prisma.inviteLink.create as jest.Mock).mockResolvedValue({ id: 'inv1', code: 'abc123xyz' })
  const req = new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ type: 'STANDARD' }),
  })
  const res = await POST_INVITE(req, adminCtx)
  expect(res.status).toBe(201)
  const json = await res.json()
  expect(json.inviteLink.code).toBe('abc123xyz')
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/admin-settings.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement settings route**

Create `app/api/admin/settings/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const updateSchema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters').max(60).optional(),
  isPublic: z.boolean().optional(),
  autoApproveMembers: z.boolean().optional(),
  discordServerId: z.string().nullable().optional(),
})

export const GET = withCommunityAuth(async (_req: Request, ctx: CommunityContext) => {
  const community = await prisma.community.findUnique({
    where: { id: ctx.communityId },
    select: { id: true, name: true, slug: true, logo: true, isPublic: true, autoApproveMembers: true, discordServerId: true },
  })
  return NextResponse.json({ settings: community })
}, { adminOnly: true })

export const PATCH = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const body = await req.json()
  const parsed = updateSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const community = await prisma.community.update({
    where: { id: ctx.communityId },
    data: parsed.data,
  })

  return NextResponse.json({ community })
}, { adminOnly: true })
```

- [ ] **Step 4: Implement invites route**

First check if `nanoid` is installed:

```bash
npm list nanoid 2>/dev/null | head -3
```

If not installed: `npm install nanoid`

Create `app/api/admin/invites/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { nanoid } from 'nanoid'
import { withCommunityAuth } from '@/lib/community-auth'
import { prisma } from '@/lib/prisma'
import type { CommunityContext } from '@/lib/community-auth'

const createSchema = z.object({
  type: z.enum(['STANDARD', 'DIRECT_ADMIT']).default('STANDARD'),
  maxUses: z.number().int().positive().optional(),
  expiresAt: z.string().datetime().optional(),
})

export const POST = withCommunityAuth(async (req: Request, ctx: CommunityContext) => {
  const body = await req.json()
  const parsed = createSchema.safeParse(body)
  if (!parsed.success) {
    return NextResponse.json({ error: parsed.error.issues[0].message }, { status: 400 })
  }

  const inviteLink = await prisma.inviteLink.create({
    data: {
      communityId: ctx.communityId,
      createdBy: ctx.userId,
      code: nanoid(10),
      type: parsed.data.type,
      maxUses: parsed.data.maxUses,
      expiresAt: parsed.data.expiresAt ? new Date(parsed.data.expiresAt) : undefined,
    },
  })

  return NextResponse.json({ inviteLink }, { status: 201 })
}, { adminOnly: true })
```

- [ ] **Step 5: Create settings admin page**

Create `app/(app)/admin/settings/page.tsx`:

```typescript
'use client'
import { useState, useEffect } from 'react'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'

interface Settings {
  name: string
  isPublic: boolean
  autoApproveMembers: boolean
  discordServerId: string | null
}

export default function AdminSettingsPage() {
  const [settings, setSettings] = useState<Settings | null>(null)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)

  useEffect(() => {
    fetch('/api/admin/settings')
      .then(r => r.json())
      .then(j => setSettings(j.settings))
  }, [])

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!settings) return
    setSaving(true)
    setError('')
    setSuccess(false)
    const res = await fetch('/api/admin/settings', {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(settings),
    })
    const json = await res.json()
    if (!res.ok) { setError(json.error); setSaving(false); return }
    setSuccess(true)
    setSaving(false)
  }

  if (!settings) return <div className="text-sm text-text-muted">Loading...</div>

  return (
    <div className="max-w-lg">
      <h1 className="text-xl font-semibold tracking-tight mb-6">Community Settings</h1>
      <form onSubmit={onSubmit} className="flex flex-col gap-5">
        <Input
          label="Community Name"
          id="name"
          value={settings.name}
          onChange={e => setSettings(s => s && { ...s, name: e.target.value })}
        />
        <label className="flex items-center gap-2 text-sm text-text-muted cursor-pointer">
          <input
            type="checkbox"
            checked={settings.isPublic}
            onChange={e => setSettings(s => s && { ...s, isPublic: e.target.checked })}
            className="accent-white"
          />
          Public community (members can join via invite link)
        </label>
        <label className="flex items-center gap-2 text-sm text-text-muted cursor-pointer">
          <input
            type="checkbox"
            checked={settings.autoApproveMembers}
            onChange={e => setSettings(s => s && { ...s, autoApproveMembers: e.target.checked })}
            className="accent-white"
          />
          Auto-approve new members
        </label>
        <Input
          label="Discord Server ID (optional)"
          id="discord"
          value={settings.discordServerId ?? ''}
          onChange={e => setSettings(s => s && { ...s, discordServerId: e.target.value || null })}
        />
        {error && <p className="text-xs text-danger">{error}</p>}
        {success && <p className="text-xs text-text-muted">Settings saved.</p>}
        <Button type="submit" loading={saving}>Save Settings</Button>
      </form>
    </div>
  )
}
```

- [ ] **Step 6: Run tests — expect PASS**

```bash
npx jest __tests__/api/admin-settings.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 7: Commit**

```bash
git add app/api/admin/settings/route.ts app/api/admin/invites/route.ts app/'(app)'/admin/settings/page.tsx __tests__/api/admin-settings.test.ts
git commit -m "feat: add admin settings, invite links API and settings page"
```

---

## Task 13: Join via invite link

**Files:**
- Create: `app/api/community/join/route.ts`
- Modify: `app/onboarding/page.tsx`
- Test: `__tests__/api/community-join.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/api/community-join.test.ts`:

```typescript
import { POST } from '@/app/api/community/join/route'

jest.mock('@/lib/auth', () => ({ auth: jest.fn() }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    inviteLink: { findFirst: jest.fn(), update: jest.fn() },
    communityMember: { findFirst: jest.fn(), create: jest.fn() },
    community: { findUnique: jest.fn() },
    application: { create: jest.fn() },
  },
}))

import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'

const mockAuth = auth as jest.Mock
const mockInvite = prisma.inviteLink.findFirst as jest.Mock
const mockMemberFind = prisma.communityMember.findFirst as jest.Mock
const mockMemberCreate = prisma.communityMember.create as jest.Mock
const mockCommunity = prisma.community.findUnique as jest.Mock
const mockAppCreate = prisma.application.create as jest.Mock

const validInvite = {
  id: 'inv1', code: 'abc123', communityId: 'c1', type: 'DIRECT_ADMIT',
  isActive: true, maxUses: null, useCount: 0, expiresAt: null,
}
const publicCommunity = { id: 'c1', isPublic: true, autoApproveMembers: true }

beforeEach(() => jest.clearAllMocks())

function req(body: object) {
  return new Request('http://localhost', {
    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
  })
}

test('returns 401 if not authenticated', async () => {
  mockAuth.mockResolvedValue(null)
  expect((await POST(req({ code: 'abc123' }))).status).toBe(401)
})

test('returns 400 if code missing', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  expect((await POST(req({}))).status).toBe(400)
})

test('returns 404 if invite not found', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockInvite.mockResolvedValue(null)
  expect((await POST(req({ code: 'bad' }))).status).toBe(404)
})

test('returns 409 if already a member', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockInvite.mockResolvedValue(validInvite)
  mockMemberFind.mockResolvedValue({ id: 'm1' })
  expect((await POST(req({ code: 'abc123' }))).status).toBe(409)
})

test('DIRECT_ADMIT invite creates ACTIVE member directly', async () => {
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockInvite.mockResolvedValue(validInvite)
  mockMemberFind.mockResolvedValue(null)
  mockMemberCreate.mockResolvedValue({ id: 'm1', status: 'ACTIVE' })
  ;(prisma.inviteLink.update as jest.Mock).mockResolvedValue({})
  const res = await POST(req({ code: 'abc123' }))
  expect(res.status).toBe(201)
  expect(mockMemberCreate).toHaveBeenCalledWith(
    expect.objectContaining({ data: expect.objectContaining({ status: 'ACTIVE' }) })
  )
})

test('STANDARD invite on public+autoApprove community creates ACTIVE member', async () => {
  const standardInvite = { ...validInvite, type: 'STANDARD' }
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockInvite.mockResolvedValue(standardInvite)
  mockMemberFind.mockResolvedValue(null)
  mockCommunity.mockResolvedValue(publicCommunity)
  mockMemberCreate.mockResolvedValue({ id: 'm1', status: 'ACTIVE' })
  ;(prisma.inviteLink.update as jest.Mock).mockResolvedValue({})
  const res = await POST(req({ code: 'abc123' }))
  expect(res.status).toBe(201)
})

test('STANDARD invite on private community creates application', async () => {
  const standardInvite = { ...validInvite, type: 'STANDARD' }
  mockAuth.mockResolvedValue({ user: { id: 'u1' } })
  mockInvite.mockResolvedValue(standardInvite)
  mockMemberFind.mockResolvedValue(null)
  mockCommunity.mockResolvedValue({ id: 'c1', isPublic: false, autoApproveMembers: false })
  mockAppCreate.mockResolvedValue({ id: 'app1', status: 'PENDING' })
  ;(prisma.inviteLink.update as jest.Mock).mockResolvedValue({})
  const res = await POST(req({ code: 'abc123' }))
  expect(res.status).toBe(202)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/community-join.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement join route**

Create `app/api/community/join/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'

export async function POST(req: Request) {
  const session = await auth()
  if (!session?.user?.id) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })

  const body = await req.json()
  const code: string | undefined = body?.code
  if (!code) return NextResponse.json({ error: 'code required' }, { status: 400 })

  const invite = await prisma.inviteLink.findFirst({
    where: {
      code,
      isActive: true,
      OR: [{ expiresAt: null }, { expiresAt: { gt: new Date() } }],
    },
  })
  if (!invite) return NextResponse.json({ error: 'Invalid or expired invite' }, { status: 404 })

  if (invite.maxUses !== null && invite.useCount >= invite.maxUses) {
    return NextResponse.json({ error: 'Invite link has reached its usage limit' }, { status: 410 })
  }

  const existing = await prisma.communityMember.findFirst({
    where: { communityId: invite.communityId, userId: session.user.id },
  })
  if (existing) return NextResponse.json({ error: 'Already a member' }, { status: 409 })

  await prisma.inviteLink.update({
    where: { id: invite.id },
    data: { useCount: { increment: 1 } },
  })

  if (invite.type === 'DIRECT_ADMIT') {
    const member = await prisma.communityMember.create({
      data: { communityId: invite.communityId, userId: session.user.id, status: 'ACTIVE' },
    })
    return NextResponse.json({ member }, { status: 201 })
  }

  // STANDARD invite: check community settings
  const community = await prisma.community.findUnique({
    where: { id: invite.communityId },
    select: { isPublic: true, autoApproveMembers: true },
  })

  if (community?.isPublic && community.autoApproveMembers) {
    const member = await prisma.communityMember.create({
      data: { communityId: invite.communityId, userId: session.user.id, status: 'ACTIVE' },
    })
    return NextResponse.json({ member }, { status: 201 })
  }

  // Private community: create pending application
  const application = await prisma.application.create({
    data: {
      communityId: invite.communityId,
      applicantUserId: session.user.id,
      status: 'PENDING',
      formData: {},
    },
  })
  return NextResponse.json({ application, status: 'pending' }, { status: 202 })
}
```

- [ ] **Step 4: Update onboarding page to add join tab**

In `app/onboarding/page.tsx`, add a `JoinForm` inner component alongside `OnboardingForm`. Update `OnboardingPage` to include a tab switcher between "Create" and "Join":

```typescript
// Replace the export default function entirely with:

function JoinForm() {
  const [code, setCode] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [pending, setPending] = useState(false)
  const router = useRouter()

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError('')
    const res = await fetch('/api/community/join', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code }),
    })
    const json = await res.json()
    if (res.status === 201) { router.push('/dashboard'); return }
    if (res.status === 202) { setPending(true); setLoading(false); return }
    setError(json.error)
    setLoading(false)
  }

  if (pending) {
    return (
      <div className="text-center">
        <p className="text-sm text-text-muted mb-4">Your application has been submitted and is pending review.</p>
        <Button onClick={() => router.push('/onboarding')}>Back</Button>
      </div>
    )
  }

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4">
      <Input label="Invite Code" id="code" value={code} onChange={e => setCode(e.target.value)} placeholder="e.g. abc123xyz" />
      {error && <p className="text-xs text-danger">{error}</p>}
      <Button type="submit" loading={loading} className="w-full">Join Community</Button>
    </form>
  )
}
```

Add a `tab` state to `OnboardingPage` (wrap the whole thing in Suspense and extract inner component):

```typescript
function OnboardingInner() {
  const router = useRouter()
  const params = useSearchParams()
  const success = params.get('success')
  const [tab, setTab] = useState<'create' | 'join'>('create')
  // ... existing success screen, then tab switcher + conditionally render OnboardingForm or JoinForm
}
```

The full updated file should have:
- `OnboardingForm` (existing form logic, extracted)
- `JoinForm` (new)
- `OnboardingInner` (wraps both with a tab bar, handles `?success=1`)
- `export default function OnboardingPage() { return <Suspense><OnboardingInner /></Suspense> }`

**Note for implementer:** Read the existing `app/onboarding/page.tsx` carefully before editing it. Preserve all existing logic — only add the `JoinForm` and tab switcher on top of it.

- [ ] **Step 5: Run tests — expect PASS**

```bash
npx jest __tests__/api/community-join.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 6: Commit**

```bash
git add app/api/community/join/route.ts app/onboarding/page.tsx __tests__/api/community-join.test.ts
git commit -m "feat: add invite link join flow and onboarding tab switcher"
```

---

## Task 14: Audit log utility + full test run

**Files:**
- Create: `lib/audit.ts`
- Test: `__tests__/lib/audit.test.ts`

- [ ] **Step 1: Write the failing tests**

Create `__tests__/lib/audit.test.ts`:

```typescript
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
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/lib/audit.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 3: Implement `lib/audit.ts`**

```typescript
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
      data: { communityId, actorId, action, targetType, targetId, metadata },
    })
  } catch {
    // Audit log failures must never break the main request
  }
}
```

- [ ] **Step 4: Run — expect PASS**

```bash
npx jest __tests__/lib/audit.test.ts --no-coverage 2>&1 | tail -15
```

- [ ] **Step 5: Run full test suite**

```bash
npx jest --passWithNoTests --no-coverage 2>&1 | tail -20
```

All tests should pass. Fix any regressions before committing.

- [ ] **Step 6: TypeScript check**

```bash
npx tsc --noEmit 2>&1 | head -40
```

Fix all TypeScript errors before committing.

- [ ] **Step 7: Commit**

```bash
git add lib/audit.ts __tests__/lib/audit.test.ts
git commit -m "feat: add audit log utility — Phase 2 complete"
```

---

## Phase 2 Complete

At this point the app has:
- Community context infrastructure (`withCommunityAuth`, `CommunityProvider`, `useCommunity`)
- App layout that resolves and injects active community server-side
- Community switcher popover in the icon bar
- Community switch API (sets `active_community_id` cookie)
- Dashboard with stat cards and recent members
- Announcements: list + create (admin/mod only)
- Events: list + create (admin/mod only)
- Roster: member directory with department filter
- Messages: inbox + compose
- Admin: Applications review (approve/deny)
- Admin: Members management (role/status/dept/rank update, remove)
- Admin: Departments + Ranks CRUD with plan limit enforcement
- Admin: Settings (name, privacy, Discord server ID)
- Admin: Invite link creation
- Join flow: invite code → DIRECT_ADMIT (instant) or STANDARD (auto-approve or pending application)
- Audit log utility (fire-and-forget, called in admin routes)

**Next:** Phase 3 — Standard/Pro feature pages (Patrol Logs, Shifts, SOPs, Documents, LOA, Chain of Command, Transfers), account settings, session management, and plan gating UI.
