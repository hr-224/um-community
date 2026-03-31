# CommunityOS — Full Rebuild Design Spec
**Date:** 2026-03-30
**Project:** community.ultmods.com
**Author:** Ultimate Mods LLC

---

## Overview

A complete ground-up rebuild of the FiveM Community Manager as a multi-tenant SaaS platform. The product is sold to FiveM roleplay communities under one hosted domain (`community.ultmods.com`). Community owners purchase a plan tier via Stripe, create a workspace, and manage their community entirely within the app. Users can belong to multiple communities and switch between them seamlessly.

The current PHP codebase is being replaced entirely. No feature is being ported as-is — everything is rebuilt clean.

---

## Tech Stack

| Layer | Choice |
|---|---|
| Frontend | Next.js 14 (App Router) + React |
| Styling | Tailwind CSS + custom design tokens |
| Auth | NextAuth.js — email/password + Discord OAuth + TOTP 2FA |
| API | Next.js API Route Handlers + Server Actions |
| ORM | Prisma → MySQL |
| Payments | Stripe (subscriptions + webhooks + customer portal) |
| Multi-tenancy | React Context + cookie-based community scope |
| Hosting | Single domain: `community.ultmods.com` |

---

## Design System

**Theme:** Monochrome Minimal — pure blacks/whites, zero color accent.
**Layout:** Icon bar (60px) + labeled sidebar (220px) + main content area.
**Reference mockup:** `.superpowers/brainstorm/*/content/design-hybrid.html`

### Color Tokens
- `--bg-base`: `#0a0a0a`
- `--bg-surface`: `#0f0f0f`
- `--bg-elevated`: `#141414`
- `--border`: `#1c1c1c`
- `--border-light`: `#222222`
- `--text-primary`: `#ffffff`
- `--text-secondary`: `#888888`
- `--text-muted`: `#444444`
- `--text-faint`: `#2e2e2e`
- `--accent`: `#ffffff` (white — buttons, active states, highlights)
- `--success`: `#4a7a4a` (muted green)
- `--warning`: `#6a5a30` (muted amber)
- `--danger`: `#6a3030` (muted red)

### Layout Rules
- Icon bar: fixed 60px, icons only, tooltips on hover, community switcher at top
- Sidebar: 220px, labeled nav groups, active item has `#141414` background + white text
- Topbar: 48px, logo zone aligned with icon bar, breadcrumb, search, notifications, avatar
- Content: `28px 32px` padding, max-width unconstrained
- Cards: `#0f0f0f` background, `1px solid #1c1c1c` border, `8px` border-radius
- Stat cards with highlight: `border-top: 2px solid #fff`
- All status chips: muted background + muted text + muted border (no bright colors)

### Typography
- Font: Inter (system fallback: system-ui, -apple-system)
- Page title: `20px`, `font-weight: 600`, `letter-spacing: -0.5px`
- Card title: `11px`, `text-transform: uppercase`, `letter-spacing: 0.8px`, muted color
- Body: `13px`, `color: #bbb`
- Muted: `12px`, `color: #666`

---

## App Zones & Routing

### 1. Public Zone
Routes: `/`, `/login`, `/register`, `/auth/discord/callback`, `/pricing`, `/forgot-password`, `/reset-password`
No authentication required. Marketing page, login/register forms, Discord OAuth callback handler.

### 2. Onboarding Zone
Route: `/onboarding`
Requires auth. Shown to users who have no community memberships.
Options:
- **Create a Community** → choose name → select plan → Stripe Checkout → community created → redirect to dashboard
- **Join via Invite Link** → only available if the target community has public join enabled

### 3. Community Zone
Routes: `/dashboard`, `/roster`, `/announcements`, `/events`, `/messages`, `/patrol-logs`, `/shifts`, `/sops`, `/documents`, `/loa`, `/chain-of-command`, `/transfers`, `/quizzes`, `/analytics`, `/custom-fields`, `/mentorships`, `/recognition`, `/audit-log`, `/api-keys`, `/admin/*`
Requires auth + active community context (cookie + React context).
Feature access gated by community plan tier (checked server-side on every request).

### 4. Account Zone
Routes: `/account`, `/account/security`, `/account/notifications`, `/account/sessions`
User-level settings, independent of community context.

### 5. Super Admin Zone
Route: `/superadmin/*`
Completely hidden — no links from the main UI. Accessible only to the platform owner account (checked by hardcoded user ID or a `isSuperAdmin` flag on the User model). Separate layout, no community context.

---

## Multi-Tenancy Architecture

### Community Context
- Active community stored in a cookie (`active_community_id`) and a React Context (`CommunityContext`)
- On app load, middleware reads the cookie and validates the user is a member of that community
- If invalid or missing, user is redirected to `/onboarding`
- All API route handlers extract `communityId` from the validated session — never from user input
- All Prisma queries at community level always include `where: { communityId }` — enforced by a query wrapper utility

### Community Switcher
- Lives in the icon bar, top position
- Opens a popover listing all communities the user belongs to, showing their role in each
- One click sets the cookie, updates React context, re-fetches active page data
- No URL change

### Community Privacy
- **Public:** prospective members can join via invite link → added as pending → admin approves (or auto-approved if setting enabled)
- **Private:** no direct join. Prospective members must submit a formal application. Admins review and approve/deny. Invite links only bypass this if explicitly generated as a "direct admit" link by an admin.

---

## Data Model

### Platform Level

**User**
```
id, email, emailVerified, passwordHash, discordId, discordUsername,
avatar, totpSecret, totpEnabled, isSuperAdmin, createdAt, updatedAt
```

**Community**
```
id, name, slug, logo, ownerId (→ User), planTier (FREE|STANDARD|PRO),
stripeCustomerId, stripeSubscriptionId, subscriptionStatus,
isPublic, autoApproveMembers, discordServerId, discordBotToken,
status (ACTIVE|SUSPENDED|CANCELLED), createdAt, updatedAt
```
Note: `slug` is used for invite link generation and internal references only — it does NOT appear in the app URL. The app URL never changes regardless of active community.

**CommunityMember**
```
id, communityId (→ Community), userId (→ User), role (OWNER|ADMIN|MODERATOR|MEMBER),
departmentId (→ Department), rankId (→ Rank), callsign, status (ACTIVE|LOA|INACTIVE|SUSPENDED),
joinedAt, customFields (JSON)
```

**Subscription**
```
id, communityId, stripeSubscriptionId, stripePriceId, planTier,
status, currentPeriodStart, currentPeriodEnd, cancelAtPeriodEnd, updatedAt
```

### Community Level

**Department** — `id, communityId, name, description, color, sortOrder`
**Rank** — `id, communityId, departmentId, name, level, isCommand`
**Application** — `id, communityId, applicantUserId, status, formData (JSON), reviewedBy, reviewedAt, notes`
**Announcement** — `id, communityId, authorId, title, content, isPinned, publishedAt`
**Event** — `id, communityId, authorId, title, description, startAt, endAt, location, rsvps`
**Message** — `id, communityId, senderId, recipientId, content, readAt`
**Document** — `id, communityId, uploadedBy, name, fileUrl, category`
**SOP** — `id, communityId, authorId, title, content, version, publishedAt`
**PatrolLog** — `id, communityId, memberId, startTime, endTime, notes, departmentId`
**Shift** — `id, communityId, title, startAt, endAt, slots, signups`
**LOA** — `id, communityId, memberId, startDate, endDate, reason, status, approvedBy`
**Transfer** — `id, communityId, memberId, fromDeptId, toDeptId, reason, status, reviewedBy`
**Quiz** — `id, communityId, title, questions (JSON), passingScore, departmentId`
**QuizResult** — `id, quizId, memberId, score, passed, completedAt, answers (JSON)`
**AuditLog** — `id, communityId, actorId, action, targetType, targetId, metadata (JSON), createdAt`
**Notification** — `id, communityId, userId, type, title, body, readAt, createdAt`
**ApiKey** — `id, communityId, name, keyHash, lastUsedAt, createdBy`
**InviteLink** — `id, communityId, createdBy, code (unique), type (STANDARD|DIRECT_ADMIT), maxUses, useCount, expiresAt, isActive`
Note: `STANDARD` invite links respect the community's privacy setting (public = auto-adds as pending, private = rejected). `DIRECT_ADMIT` links bypass the application process and are admin-generated for specific individuals.

---

## Authentication

### Email/Password
- NextAuth credentials provider
- bcrypt password hashing (cost factor 12)
- Email verification required before login
- Password reset via signed email link (expires 1 hour)
- Password policy: min 8 chars, enforced server-side

### Discord OAuth
- NextAuth Discord provider (rebuilt from scratch)
- On callback: check if `discordId` matches existing user → link session; if not → create user account
- Store Discord avatar, username, discriminator on user record
- Users can link/unlink Discord from `/account/security`

### 2FA (TOTP)
- Optional per user, set up from `/account/security`
- Community admins can require 2FA for all members (enforced on login for that community context)
- TOTP secret stored encrypted on User record
- Backup codes generated on setup (8 codes, one-time use)

### Session
- NextAuth JWT session stored in httpOnly cookie — contains: `userId`, `email` only
- Active community stored separately in a second httpOnly cookie (`active_community_id`) — set on community selection, validated server-side on every request
- Session validated on every API request via NextAuth `getServerSession()`
- Community membership validated separately: middleware confirms the user is an active member of the `active_community_id` community before granting access to community-zone routes

---

## Billing & Plan Enforcement

### Plans

| Feature | Free | Standard | Pro |
|---|---|---|---|
| Members | 15 | 75 | Unlimited |
| Departments | 1 | 5 | Unlimited |
| Announcements, Roster, Applications, Events, Messages | ✓ | ✓ | ✓ |
| Patrol Logs, Shifts, SOPs, Documents, LOA, Chain of Command | — | ✓ | ✓ |
| Discord Integration | — | ✓ | ✓ |
| Quizzes, Analytics, Custom Fields, Mentorships, Recognition, Audit Log, API Keys | — | — | ✓ |

Plan limits are defined in a config file (`lib/plans.ts`), not the database, so they can be updated without migrations.

### Stripe Integration
- Community creation triggers Stripe Checkout session (or skips for Free tier)
- Successful payment webhook (`checkout.session.completed`) creates the community and subscription record
- Community owners access billing via a Stripe Customer Portal link (no custom billing UI)
- Webhooks handled at `/api/webhooks/stripe`:
  - `customer.subscription.updated` → update plan tier + subscription record
  - `customer.subscription.deleted` → mark community as cancelled
  - `invoice.payment_failed` → flag community, send owner email
- Grace period: 7 days after payment failure before community is suspended (read-only mode)
- Suspended communities: members see a banner, no new data can be written, owner directed to billing

### Plan Enforcement
- Server-side utility: `checkPlanLimit(communityId, limitType)` — throws if limit exceeded
- Called in API route handlers before any write operation that could hit a limit
- UI shows usage indicators (e.g. "12/15 members") and upgrade prompts when limits are approached (>80%)

---

## Feature Pages

### Community Zone — All Tiers
- **Dashboard** — stat cards (members, online, applications, pending), active roster table, recent activity feed, department breakdown
- **Announcements** — list with pinned at top, rich text editor, read receipts
- **Events** — calendar + list view, RSVP, attendance tracking
- **Messages** — direct message inbox/compose
- **Notifications** — in-app notification center
- **Account** — profile, avatar, name, email
- **Account / Security** — password change, Discord link/unlink, 2FA setup, active sessions

### Community Zone — Standard + Pro
- **Roster** — member directory, filter by department/rank/status, export
- **Patrol Logs** — submit log (dept, start/end time, notes), view history
- **Shifts** — weekly schedule, sign-up, admin management
- **SOPs** — versioned documents, department filter
- **Documents** — file library with categories
- **LOA** — submit request, admin approval, calendar overlay
- **Chain of Command** — visual org chart by department
- **Transfers** — submit inter-department transfer request, admin review

### Community Zone — Pro Only
- **Quizzes** — create/edit quizzes, assign to departments, view results
- **Analytics** — member activity trends, patrol hours, application funnel
- **Custom Fields** — add fields to member profiles (text, select, date, etc.)
- **Mentorships** — assign mentor/mentee pairs, track progress
- **Recognition** — award badges and commendations, public record
- **Audit Log** — full chronological action history, filterable
- **API Keys** — generate and manage API keys for external integrations

### Community Admin Panel (`/admin/*`)
- **Settings** — name, logo, privacy (public/private), invite settings, auto-approve toggle, Discord server link
- **Applications** — review queue, approve/deny with notes, bulk actions
- **Members** — promote, demote, suspend, remove, edit custom fields
- **Departments** — create/edit/delete departments and ranks
- **Roles & Permissions** — granular permission assignments per role
- **Promotions** — bulk promotion workflows
- **Webhooks** — configure outbound webhooks (e.g. Discord channel notifications)
- **Billing** — current plan, usage stats, link to Stripe Customer Portal

### Super Admin Panel (`/superadmin/*`)
- **Communities** — full list, plan tier, subscription status, member count, MRR contribution
- **Users** — search any user across all communities
- **Subscriptions** — Stripe subscription overview, manual plan override, comp a plan
- **Impersonate** — view any community as its admin (for support/debugging)
- **System** — error logs, active session count, DB health

---

## Key Utilities & Patterns

- `withCommunityAuth(handler)` — API route wrapper: validates session, extracts + validates communityId from cookie, injects into handler context
- `checkPlanLimit(communityId, type)` — throws `PlanLimitError` if action exceeds tier
- `checkFeatureAccess(communityId, feature)` — throws `FeatureGatedError` if feature not in plan
- `createAuditLog(communityId, actorId, action, target)` — called after every significant write
- `sendNotification(userId, communityId, type, data)` — creates in-app notification record
- All DB queries go through Prisma client singleton (`lib/prisma.ts`)
- Stripe client singleton (`lib/stripe.ts`)
- Plan config (`lib/plans.ts`) — single source of truth for tier limits and feature flags
