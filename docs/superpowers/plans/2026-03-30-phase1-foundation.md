# CommunityOS Phase 1: Foundation & Auth

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Scaffold the Next.js application, establish the design system, define the database schema, and implement complete authentication (email/password, Discord OAuth, 2FA) with onboarding and Stripe-gated community creation.

**Architecture:** Next.js 14 App Router monorepo at `/var/www/community.ultmods.com`. Prisma connects to existing MySQL. NextAuth handles all auth flows. Community creation is gated behind Stripe Checkout for paid tiers.

**Tech Stack:** Next.js 14, TypeScript, Tailwind CSS, Prisma, MySQL, NextAuth.js, Stripe, speakeasy (TOTP), nodemailer

---

## File Map

```
/var/www/community.ultmods.com/
├── app/
│   ├── (auth)/
│   │   ├── login/page.tsx
│   │   ├── register/page.tsx
│   │   ├── forgot-password/page.tsx
│   │   ├── reset-password/page.tsx
│   │   └── layout.tsx
│   ├── (app)/
│   │   ├── dashboard/page.tsx          # stub — Phase 2
│   │   └── layout.tsx                  # AppShell wrapper
│   ├── onboarding/page.tsx
│   ├── account/
│   │   └── security/page.tsx           # 2FA setup
│   ├── api/
│   │   ├── auth/[...nextauth]/route.ts
│   │   ├── webhooks/stripe/route.ts
│   │   ├── community/create/route.ts
│   │   ├── auth/2fa/setup/route.ts
│   │   ├── auth/2fa/verify/route.ts
│   │   └── auth/2fa/disable/route.ts
│   ├── layout.tsx
│   └── globals.css
├── components/
│   ├── layout/
│   │   ├── AppShell.tsx
│   │   ├── IconBar.tsx
│   │   ├── Sidebar.tsx
│   │   ├── Topbar.tsx
│   │   └── CommunitySwitcher.tsx       # stub — Phase 2
│   └── ui/
│       ├── Button.tsx
│       ├── Card.tsx
│       ├── Input.tsx
│       ├── Badge.tsx
│       └── Spinner.tsx
├── lib/
│   ├── prisma.ts
│   ├── auth.ts                         # NextAuth config
│   ├── stripe.ts
│   ├── plans.ts
│   ├── email.ts
│   └── totp.ts
├── middleware.ts
├── prisma/
│   └── schema.prisma
├── types/index.ts
├── tailwind.config.ts
├── next.config.ts
└── package.json
```

---

### Task 1: Archive legacy PHP files and scaffold Next.js

**Files:**
- Create: `package.json`, `next.config.ts`, `tsconfig.json`, `tailwind.config.ts`

- [ ] **Step 1: Archive legacy code**

```bash
cd /var/www/community.ultmods.com
mkdir -p _legacy
# Move all PHP/legacy files except docs and .superpowers
find . -maxdepth 1 \
  ! -name '.' \
  ! -name '_legacy' \
  ! -name 'docs' \
  ! -name '.superpowers' \
  ! -name '.git' \
  -exec mv {} _legacy/ \;
```

- [ ] **Step 2: Initialize Next.js project**

```bash
cd /var/www/community.ultmods.com
npx create-next-app@latest . \
  --typescript \
  --tailwind \
  --eslint \
  --app \
  --no-src-dir \
  --import-alias="@/*" \
  --yes
```

- [ ] **Step 3: Install dependencies**

```bash
npm install \
  @prisma/client \
  next-auth@beta \
  @auth/prisma-adapter \
  stripe \
  @stripe/stripe-js \
  speakeasy \
  qrcode \
  bcryptjs \
  nodemailer \
  nanoid \
  zod \
  react-hook-form \
  @hookform/resolvers

npm install -D \
  prisma \
  @types/bcryptjs \
  @types/nodemailer \
  @types/qrcode \
  @types/speakeasy \
  jest \
  jest-environment-jsdom \
  @testing-library/react \
  @testing-library/jest-dom \
  @testing-library/user-event \
  ts-jest
```

- [ ] **Step 4: Create `.env.local`**

```bash
cat > .env.local << 'EOF'
# Database
DATABASE_URL="mysql://root:password@localhost:3306/communityos"

# NextAuth
NEXTAUTH_URL="http://localhost:3000"
NEXTAUTH_SECRET="replace-with-32-char-secret"

# Discord OAuth
DISCORD_CLIENT_ID=""
DISCORD_CLIENT_SECRET=""

# Stripe
STRIPE_SECRET_KEY=""
STRIPE_WEBHOOK_SECRET=""
STRIPE_FREE_PRICE_ID=""
STRIPE_STANDARD_PRICE_ID=""
STRIPE_PRO_PRICE_ID=""

# Email (SMTP)
SMTP_HOST=""
SMTP_PORT="587"
SMTP_USER=""
SMTP_PASS=""
SMTP_FROM="noreply@community.ultmods.com"

# App
NEXT_PUBLIC_APP_URL="http://localhost:3000"
SUPERADMIN_USER_ID=""
EOF
```

- [ ] **Step 5: Configure Jest**

Create `jest.config.ts`:

```typescript
import type { Config } from 'jest'
import nextJest from 'next/jest.js'

const createJestConfig = nextJest({ dir: './' })

const config: Config = {
  coverageProvider: 'v8',
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['<rootDir>/jest.setup.ts'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/$1',
  },
}

export default createJestConfig(config)
```

Create `jest.setup.ts`:

```typescript
import '@testing-library/jest-dom'
```

- [ ] **Step 6: Commit**

```bash
git init
git add -A
git commit -m "feat: scaffold Next.js project, archive legacy PHP"
```

---

### Task 2: Tailwind design system

**Files:**
- Modify: `tailwind.config.ts`
- Modify: `app/globals.css`

- [ ] **Step 1: Write test for design token presence**

Create `__tests__/design-system.test.ts`:

```typescript
import resolveConfig from 'tailwindcss/resolveConfig'
import tailwindConfig from '@/tailwind.config'

const config = resolveConfig(tailwindConfig)

test('design tokens include expected background colors', () => {
  const colors = config.theme?.colors as Record<string, unknown>
  expect(colors['bg-base']).toBe('#0a0a0a')
  expect(colors['bg-surface']).toBe('#0f0f0f')
  expect(colors['bg-elevated']).toBe('#141414')
})

test('design tokens include border colors', () => {
  const colors = config.theme?.colors as Record<string, unknown>
  expect(colors['border-default']).toBe('#1c1c1c')
})
```

- [ ] **Step 2: Run test — expect FAIL**

```bash
npx jest __tests__/design-system.test.ts
# Expected: FAIL — colors not defined yet
```

- [ ] **Step 3: Configure Tailwind with design tokens**

Replace `tailwind.config.ts`:

```typescript
import type { Config } from 'tailwindcss'

const config: Config = {
  content: [
    './app/**/*.{ts,tsx}',
    './components/**/*.{ts,tsx}',
    './lib/**/*.{ts,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        'bg-base':     '#0a0a0a',
        'bg-surface':  '#0f0f0f',
        'bg-elevated': '#141414',
        'bg-hover':    '#1a1a1a',
        'border-default': '#1c1c1c',
        'border-light':   '#222222',
        'text-primary':   '#ffffff',
        'text-secondary': '#888888',
        'text-muted':     '#444444',
        'text-faint':     '#2e2e2e',
        'accent':         '#ffffff',
        'success':        '#4a7a4a',
        'success-bg':     '#0f180f',
        'warning':        '#6a5a30',
        'warning-bg':     '#181410',
        'danger':         '#6a3030',
        'danger-bg':      '#181010',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
      },
      borderRadius: {
        DEFAULT: '8px',
      },
    },
  },
  plugins: [],
}

export default config
```

- [ ] **Step 4: Update globals.css**

Replace `app/globals.css`:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

body {
  background-color: #0a0a0a;
  color: #ffffff;
  font-family: 'Inter', system-ui, -apple-system, sans-serif;
  -webkit-font-smoothing: antialiased;
}

/* Scrollbar */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: #0a0a0a; }
::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: #333; }
```

- [ ] **Step 5: Run test — expect PASS**

```bash
npx jest __tests__/design-system.test.ts
# Expected: PASS
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: configure Tailwind design system with monochrome tokens"
```

---

### Task 3: UI primitives

**Files:**
- Create: `components/ui/Button.tsx`
- Create: `components/ui/Input.tsx`
- Create: `components/ui/Card.tsx`
- Create: `components/ui/Badge.tsx`
- Create: `components/ui/Spinner.tsx`
- Test: `__tests__/ui/Button.test.tsx`

- [ ] **Step 1: Write Button tests**

Create `__tests__/ui/Button.test.tsx`:

```typescript
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { Button } from '@/components/ui/Button'

test('renders with label', () => {
  render(<Button>Save</Button>)
  expect(screen.getByRole('button', { name: 'Save' })).toBeInTheDocument()
})

test('calls onClick when clicked', async () => {
  const user = userEvent.setup()
  const onClick = jest.fn()
  render(<Button onClick={onClick}>Save</Button>)
  await user.click(screen.getByRole('button'))
  expect(onClick).toHaveBeenCalledTimes(1)
})

test('disabled button does not fire onClick', async () => {
  const user = userEvent.setup()
  const onClick = jest.fn()
  render(<Button onClick={onClick} disabled>Save</Button>)
  await user.click(screen.getByRole('button'))
  expect(onClick).not.toHaveBeenCalled()
})

test('shows spinner when loading', () => {
  render(<Button loading>Save</Button>)
  expect(screen.getByRole('button')).toBeDisabled()
})

test('renders ghost variant', () => {
  render(<Button variant="ghost">Cancel</Button>)
  expect(screen.getByRole('button')).toHaveClass('border-border-default')
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/ui/Button.test.tsx
# Expected: FAIL — module not found
```

- [ ] **Step 3: Implement Button**

Create `components/ui/Button.tsx`:

```typescript
import { forwardRef, ButtonHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: 'primary' | 'ghost' | 'danger'
  size?: 'sm' | 'md'
  loading?: boolean
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  ({ variant = 'primary', size = 'md', loading, disabled, className, children, ...props }, ref) => {
    const base = 'inline-flex items-center justify-center gap-2 font-medium rounded transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-white/30 disabled:opacity-50 disabled:cursor-not-allowed'

    const variants = {
      primary: 'bg-white text-black hover:bg-white/90',
      ghost:   'bg-transparent text-text-secondary border border-border-default hover:text-text-primary hover:border-border-light',
      danger:  'bg-danger-bg text-danger border border-danger/30 hover:border-danger/60',
    }

    const sizes = {
      sm: 'text-xs px-3 py-1.5',
      md: 'text-sm px-4 py-2',
    }

    return (
      <button
        ref={ref}
        disabled={disabled || loading}
        className={cn(base, variants[variant], sizes[size], className)}
        {...props}
      >
        {loading && (
          <svg className="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z" />
          </svg>
        )}
        {children}
      </button>
    )
  }
)
Button.displayName = 'Button'
```

Create `lib/utils.ts`:

```typescript
import { type ClassValue, clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
```

```bash
npm install clsx tailwind-merge
```

- [ ] **Step 4: Run — expect PASS**

```bash
npx jest __tests__/ui/Button.test.tsx
```

- [ ] **Step 5: Implement remaining primitives (no tests — purely presentational)**

Create `components/ui/Input.tsx`:

```typescript
import { forwardRef, InputHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string
  error?: string
}

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, error, className, id, ...props }, ref) => (
    <div className="flex flex-col gap-1.5">
      {label && (
        <label htmlFor={id} className="text-xs text-text-muted uppercase tracking-wider">
          {label}
        </label>
      )}
      <input
        ref={ref}
        id={id}
        className={cn(
          'bg-bg-elevated border border-border-default rounded px-3 py-2 text-sm text-text-primary placeholder:text-text-faint focus:outline-none focus:border-border-light transition-colors',
          error && 'border-danger/60',
          className
        )}
        {...props}
      />
      {error && <p className="text-xs text-danger">{error}</p>}
    </div>
  )
)
Input.displayName = 'Input'
```

Create `components/ui/Card.tsx`:

```typescript
import { HTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

interface CardProps extends HTMLAttributes<HTMLDivElement> {
  highlight?: boolean
}

export function Card({ highlight, className, children, ...props }: CardProps) {
  return (
    <div
      className={cn(
        'bg-bg-surface border border-border-default rounded p-5',
        highlight && 'border-t-2 border-t-white',
        className
      )}
      {...props}
    >
      {children}
    </div>
  )
}

export function CardTitle({ className, children, ...props }: HTMLAttributes<HTMLParagraphElement>) {
  return (
    <p className={cn('text-[11px] text-text-muted uppercase tracking-[0.8px] mb-3.5', className)} {...props}>
      {children}
    </p>
  )
}
```

Create `components/ui/Badge.tsx`:

```typescript
import { HTMLAttributes } from 'react'
import { cn } from '@/lib/utils'

type BadgeVariant = 'active' | 'loa' | 'inactive' | 'training' | 'default'

interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  variant?: BadgeVariant
}

const variants: Record<BadgeVariant, string> = {
  active:   'bg-success-bg text-success border-success/30',
  loa:      'bg-warning-bg text-warning border-warning/30',
  inactive: 'bg-danger-bg text-danger border-danger/30',
  training: 'bg-bg-elevated text-text-muted border-border-light',
  default:  'bg-bg-elevated text-text-muted border-border-default',
}

export function Badge({ variant = 'default', className, children, ...props }: BadgeProps) {
  return (
    <span
      className={cn('text-[10px] px-2 py-0.5 rounded border', variants[variant], className)}
      {...props}
    >
      {children}
    </span>
  )
}
```

Create `components/ui/Spinner.tsx`:

```typescript
import { cn } from '@/lib/utils'

export function Spinner({ className }: { className?: string }) {
  return (
    <svg
      className={cn('animate-spin h-4 w-4 text-text-muted', className)}
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
      <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4l3-3-3-3v4a8 8 0 00-8 8h4z" />
    </svg>
  )
}
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add UI primitives — Button, Input, Card, Badge, Spinner"
```

---

### Task 4: App shell layout components

**Files:**
- Create: `components/layout/AppShell.tsx`
- Create: `components/layout/IconBar.tsx`
- Create: `components/layout/Sidebar.tsx`
- Create: `components/layout/Topbar.tsx`
- Modify: `app/(app)/layout.tsx`

- [ ] **Step 1: Write layout render test**

Create `__tests__/layout/AppShell.test.tsx`:

```typescript
import { render, screen } from '@testing-library/react'
import { AppShell } from '@/components/layout/AppShell'

// Mock child components
jest.mock('@/components/layout/IconBar', () => ({ IconBar: () => <div data-testid="icon-bar" /> }))
jest.mock('@/components/layout/Sidebar', () => ({ Sidebar: () => <div data-testid="sidebar" /> }))
jest.mock('@/components/layout/Topbar', () => ({ Topbar: () => <div data-testid="topbar" /> }))

test('renders icon bar, sidebar, topbar and main content', () => {
  render(<AppShell><p>content</p></AppShell>)
  expect(screen.getByTestId('icon-bar')).toBeInTheDocument()
  expect(screen.getByTestId('sidebar')).toBeInTheDocument()
  expect(screen.getByTestId('topbar')).toBeInTheDocument()
  expect(screen.getByText('content')).toBeInTheDocument()
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/layout/AppShell.test.tsx
```

- [ ] **Step 3: Implement IconBar**

Create `components/layout/IconBar.tsx`:

```typescript
'use client'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'
import {
  LayoutDashboard, Users, FileText, Calendar,
  ClipboardList, Settings, LogOut
} from 'lucide-react'

const icons = [
  { icon: LayoutDashboard, href: '/dashboard', label: 'Dashboard' },
  { icon: Users,           href: '/roster',    label: 'Roster' },
  { icon: FileText,        href: '/sops',       label: 'SOPs' },
  { icon: Calendar,        href: '/events',     label: 'Events' },
  { icon: ClipboardList,   href: '/patrol-logs',label: 'Patrol Logs' },
]

export function IconBar() {
  const pathname = usePathname()
  return (
    <aside className="w-[60px] bg-[#080808] border-r border-border-default flex flex-col items-center py-3 gap-1 flex-shrink-0">
      {/* Community switcher placeholder — Phase 2 */}
      <div className="w-8 h-8 rounded-lg bg-white mb-3 flex-shrink-0" />
      <div className="w-6 h-px bg-border-default mb-1" />

      {icons.map(({ icon: Icon, href, label }) => (
        <Link
          key={href}
          href={href}
          title={label}
          className={cn(
            'w-9 h-9 rounded-lg flex items-center justify-center transition-colors',
            pathname.startsWith(href)
              ? 'bg-bg-elevated text-text-primary'
              : 'text-text-faint hover:text-text-muted hover:bg-bg-elevated'
          )}
        >
          <Icon size={16} />
        </Link>
      ))}

      <div className="flex-1" />
      <Link href="/admin" title="Settings" className="w-9 h-9 rounded-lg flex items-center justify-center text-text-faint hover:text-text-muted hover:bg-bg-elevated transition-colors">
        <Settings size={16} />
      </Link>
    </aside>
  )
}
```

```bash
npm install lucide-react
```

- [ ] **Step 4: Implement Sidebar**

Create `components/layout/Sidebar.tsx`:

```typescript
'use client'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { cn } from '@/lib/utils'

interface NavItem { label: string; href: string }
interface NavGroup { label: string; items: NavItem[] }

const navigation: NavGroup[] = [
  {
    label: 'General',
    items: [
      { label: 'Dashboard',     href: '/dashboard' },
      { label: 'Announcements', href: '/announcements' },
      { label: 'Events',        href: '/events' },
      { label: 'Messages',      href: '/messages' },
    ],
  },
  {
    label: 'Personnel',
    items: [
      { label: 'Roster',           href: '/roster' },
      { label: 'Departments',      href: '/admin/departments' },
      { label: 'Chain of Command', href: '/chain-of-command' },
      { label: 'Applications',     href: '/admin/applications' },
      { label: 'Transfers',        href: '/transfers' },
    ],
  },
  {
    label: 'Operations',
    items: [
      { label: 'Patrol Logs', href: '/patrol-logs' },
      { label: 'Shifts',      href: '/shifts' },
      { label: 'SOPs',        href: '/sops' },
      { label: 'Documents',   href: '/documents' },
      { label: 'LOA',         href: '/loa' },
    ],
  },
]

export function Sidebar() {
  const pathname = usePathname()
  return (
    <aside className="w-[220px] bg-bg-base border-r border-border-default flex flex-col gap-5 px-2.5 py-4 overflow-y-auto flex-shrink-0">
      {navigation.map((group) => (
        <div key={group.label}>
          <p className="text-[10px] text-text-faint uppercase tracking-[1px] px-2 mb-1">
            {group.label}
          </p>
          {group.items.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                'flex items-center gap-2 px-2 py-1.5 rounded text-xs transition-colors mb-0.5',
                pathname === item.href
                  ? 'bg-bg-elevated text-text-primary'
                  : 'text-text-muted hover:bg-bg-elevated hover:text-text-secondary'
              )}
            >
              {item.label}
            </Link>
          ))}
        </div>
      ))}
    </aside>
  )
}
```

- [ ] **Step 5: Implement Topbar**

Create `components/layout/Topbar.tsx`:

```typescript
'use client'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { Bell, Search } from 'lucide-react'

export function Topbar() {
  const pathname = usePathname()
  const crumb = pathname.split('/').filter(Boolean).join(' / ')

  return (
    <header className="h-12 bg-bg-base border-b border-border-default flex items-center justify-between px-5 flex-shrink-0">
      <p className="text-xs text-text-faint">
        {crumb || 'dashboard'}
      </p>
      <div className="flex items-center gap-2.5">
        <div className="flex items-center gap-2 bg-bg-elevated border border-border-default rounded px-3 py-1.5 w-44">
          <Search size={12} className="text-text-faint" />
          <span className="text-xs text-text-faint">Search...</span>
        </div>
        <button className="w-7 h-7 flex items-center justify-center text-text-faint hover:text-text-secondary transition-colors">
          <Bell size={15} />
        </button>
        <Link href="/account" className="w-7 h-7 rounded-full bg-bg-elevated border border-border-default" />
      </div>
    </header>
  )
}
```

- [ ] **Step 6: Implement AppShell**

Create `components/layout/AppShell.tsx`:

```typescript
import { ReactNode } from 'react'
import { IconBar } from './IconBar'
import { Sidebar } from './Sidebar'
import { Topbar } from './Topbar'

export function AppShell({ children }: { children: ReactNode }) {
  return (
    <div className="flex h-screen overflow-hidden bg-bg-base">
      <IconBar />
      <div className="flex flex-col flex-1 overflow-hidden">
        <Topbar />
        <div className="flex flex-1 overflow-hidden">
          <Sidebar />
          <main className="flex-1 overflow-y-auto p-7">
            {children}
          </main>
        </div>
      </div>
    </div>
  )
}
```

- [ ] **Step 7: Create app zone layout**

Create `app/(app)/layout.tsx`:

```typescript
import { AppShell } from '@/components/layout/AppShell'
import { ReactNode } from 'react'

export default function AppLayout({ children }: { children: ReactNode }) {
  return <AppShell>{children}</AppShell>
}
```

Create stub `app/(app)/dashboard/page.tsx`:

```typescript
export default function DashboardPage() {
  return (
    <div>
      <h1 className="text-xl font-semibold tracking-tight">Dashboard</h1>
      <p className="text-sm text-text-muted mt-1">Phase 2 — coming soon</p>
    </div>
  )
}
```

- [ ] **Step 8: Run test — expect PASS**

```bash
npx jest __tests__/layout/AppShell.test.tsx
```

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add AppShell layout — IconBar, Sidebar, Topbar"
```

---

### Task 5: Prisma schema and initial migration

**Files:**
- Create: `prisma/schema.prisma`
- Create: `lib/prisma.ts`

- [ ] **Step 1: Initialize Prisma**

```bash
npx prisma init --datasource-provider mysql
```

- [ ] **Step 2: Write schema**

Replace `prisma/schema.prisma`:

```prisma
generator client {
  provider = "prisma-client-js"
}

datasource db {
  provider = "mysql"
  url      = env("DATABASE_URL")
}

enum PlanTier {
  FREE
  STANDARD
  PRO
}

enum CommunityStatus {
  ACTIVE
  SUSPENDED
  CANCELLED
}

enum MemberRole {
  OWNER
  ADMIN
  MODERATOR
  MEMBER
}

enum MemberStatus {
  ACTIVE
  LOA
  INACTIVE
  SUSPENDED
}

enum InviteLinkType {
  STANDARD
  DIRECT_ADMIT
}

model User {
  id              String    @id @default(cuid())
  email           String    @unique
  emailVerified   DateTime?
  passwordHash    String?
  discordId       String?   @unique
  discordUsername String?
  avatar          String?
  totpSecret      String?
  totpEnabled     Boolean   @default(false)
  isSuperAdmin    Boolean   @default(false)
  createdAt       DateTime  @default(now())
  updatedAt       DateTime  @updatedAt

  memberships      CommunityMember[]
  ownedCommunities Community[]       @relation("CommunityOwner")
  sessions         Session[]
  accounts         Account[]
}

model Account {
  id                String  @id @default(cuid())
  userId            String
  type              String
  provider          String
  providerAccountId String
  refresh_token     String? @db.Text
  access_token      String? @db.Text
  expires_at        Int?
  token_type        String?
  scope             String?
  id_token          String? @db.Text
  session_state     String?

  user User @relation(fields: [userId], references: [id], onDelete: Cascade)

  @@unique([provider, providerAccountId])
}

model Session {
  id           String   @id @default(cuid())
  sessionToken String   @unique
  userId       String
  expires      DateTime
  user         User     @relation(fields: [userId], references: [id], onDelete: Cascade)
}

model VerificationToken {
  identifier String
  token      String   @unique
  expires    DateTime

  @@unique([identifier, token])
}

model Community {
  id                   String          @id @default(cuid())
  name                 String
  slug                 String          @unique
  logo                 String?
  ownerId              String
  planTier             PlanTier        @default(FREE)
  stripeCustomerId     String?         @unique
  stripeSubscriptionId String?         @unique
  subscriptionStatus   String?
  isPublic             Boolean         @default(false)
  autoApproveMembers   Boolean         @default(false)
  discordServerId      String?
  discordBotToken      String?         @db.Text
  status               CommunityStatus @default(ACTIVE)
  createdAt            DateTime        @default(now())
  updatedAt            DateTime        @updatedAt

  owner         User              @relation("CommunityOwner", fields: [ownerId], references: [id])
  members       CommunityMember[]
  departments   Department[]
  subscription  Subscription?
  inviteLinks   InviteLink[]
  announcements Announcement[]
  events        Event[]
  documents     Document[]
  sops          SOP[]
  patrolLogs    PatrolLog[]
  shifts        Shift[]
  loas          LOA[]
  transfers     Transfer[]
  quizzes       Quiz[]
  auditLogs     AuditLog[]
  notifications Notification[]
  apiKeys       ApiKey[]
  applications  Application[]
}

model CommunityMember {
  id           String       @id @default(cuid())
  communityId  String
  userId       String
  role         MemberRole   @default(MEMBER)
  departmentId String?
  rankId       String?
  callsign     String?
  status       MemberStatus @default(ACTIVE)
  customFields Json?
  joinedAt     DateTime     @default(now())

  community  Community   @relation(fields: [communityId], references: [id], onDelete: Cascade)
  user       User        @relation(fields: [userId], references: [id], onDelete: Cascade)
  department Department? @relation(fields: [departmentId], references: [id])
  rank       Rank?       @relation(fields: [rankId], references: [id])

  @@unique([communityId, userId])
  @@index([communityId])
}

model Subscription {
  id                   String   @id @default(cuid())
  communityId          String   @unique
  stripeSubscriptionId String   @unique
  stripePriceId        String
  planTier             PlanTier
  status               String
  currentPeriodStart   DateTime
  currentPeriodEnd     DateTime
  cancelAtPeriodEnd    Boolean  @default(false)
  updatedAt            DateTime @updatedAt

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)
}

model Department {
  id          String  @id @default(cuid())
  communityId String
  name        String
  description String?
  color       String?
  sortOrder   Int     @default(0)

  community Community        @relation(fields: [communityId], references: [id], onDelete: Cascade)
  members   CommunityMember[]
  ranks     Rank[]

  @@index([communityId])
}

model Rank {
  id           String  @id @default(cuid())
  communityId  String
  departmentId String
  name         String
  level        Int     @default(0)
  isCommand    Boolean @default(false)

  community  Community        @relation(fields: [communityId], references: [id], onDelete: Cascade)
  department Department       @relation(fields: [departmentId], references: [id], onDelete: Cascade)
  members    CommunityMember[]

  @@index([communityId])
}

model Application {
  id              String    @id @default(cuid())
  communityId     String
  applicantUserId String
  status          String    @default("PENDING")
  formData        Json
  reviewedBy      String?
  reviewedAt      DateTime?
  notes           String?   @db.Text
  createdAt       DateTime  @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
}

model Announcement {
  id          String   @id @default(cuid())
  communityId String
  authorId    String
  title       String
  content     String   @db.Text
  isPinned    Boolean  @default(false)
  publishedAt DateTime @default(now())
  createdAt   DateTime @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
}

model Event {
  id          String    @id @default(cuid())
  communityId String
  authorId    String
  title       String
  description String?   @db.Text
  startAt     DateTime
  endAt       DateTime?
  location    String?
  rsvps       Json?
  createdAt   DateTime  @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
}

model Message {
  id          String    @id @default(cuid())
  communityId String
  senderId    String
  recipientId String
  content     String    @db.Text
  readAt      DateTime?
  createdAt   DateTime  @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
  @@index([recipientId])
}

model Document {
  id          String   @id @default(cuid())
  communityId String
  uploadedBy  String
  name        String
  fileUrl     String
  category    String?
  createdAt   DateTime @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
}

model SOP {
  id          String   @id @default(cuid())
  communityId String
  authorId    String
  title       String
  content     String   @db.Text
  version     String   @default("1.0")
  publishedAt DateTime @default(now())
  createdAt   DateTime @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
}

model PatrolLog {
  id           String    @id @default(cuid())
  communityId  String
  memberId     String
  departmentId String?
  startTime    DateTime
  endTime      DateTime?
  notes        String?   @db.Text
  createdAt    DateTime  @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
  @@index([memberId])
}

model Shift {
  id          String   @id @default(cuid())
  communityId String
  title       String
  startAt     DateTime
  endAt       DateTime
  slots       Int      @default(0)
  signups     Json?
  createdAt   DateTime @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
}

model LOA {
  id          String    @id @default(cuid())
  communityId String
  memberId    String
  startDate   DateTime
  endDate     DateTime
  reason      String    @db.Text
  status      String    @default("PENDING")
  approvedBy  String?
  createdAt   DateTime  @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
}

model Transfer {
  id          String    @id @default(cuid())
  communityId String
  memberId    String
  fromDeptId  String
  toDeptId    String
  reason      String    @db.Text
  status      String    @default("PENDING")
  reviewedBy  String?
  createdAt   DateTime  @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
}

model Quiz {
  id           String   @id @default(cuid())
  communityId  String
  title        String
  questions    Json
  passingScore Int      @default(70)
  departmentId String?
  createdAt    DateTime @default(now())

  community Community    @relation(fields: [communityId], references: [id], onDelete: Cascade)
  results   QuizResult[]

  @@index([communityId])
}

model QuizResult {
  id          String   @id @default(cuid())
  quizId      String
  memberId    String
  score       Int
  passed      Boolean
  answers     Json
  completedAt DateTime @default(now())

  quiz Quiz @relation(fields: [quizId], references: [id], onDelete: Cascade)

  @@index([quizId])
  @@index([memberId])
}

model AuditLog {
  id          String   @id @default(cuid())
  communityId String
  actorId     String
  action      String
  targetType  String?
  targetId    String?
  metadata    Json?
  createdAt   DateTime @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
  @@index([createdAt])
}

model Notification {
  id          String    @id @default(cuid())
  communityId String
  userId      String
  type        String
  title       String
  body        String
  readAt      DateTime?
  createdAt   DateTime  @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId, userId])
}

model ApiKey {
  id          String    @id @default(cuid())
  communityId String
  name        String
  keyHash     String    @unique
  createdBy   String
  lastUsedAt  DateTime?
  createdAt   DateTime  @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([communityId])
}

model InviteLink {
  id          String         @id @default(cuid())
  communityId String
  createdBy   String
  code        String         @unique
  type        InviteLinkType @default(STANDARD)
  maxUses     Int?
  useCount    Int            @default(0)
  expiresAt   DateTime?
  isActive    Boolean        @default(true)
  createdAt   DateTime       @default(now())

  community Community @relation(fields: [communityId], references: [id], onDelete: Cascade)

  @@index([code])
}
```

- [ ] **Step 3: Create Prisma client singleton**

Create `lib/prisma.ts`:

```typescript
import { PrismaClient } from '@prisma/client'

const globalForPrisma = globalThis as unknown as { prisma: PrismaClient }

export const prisma =
  globalForPrisma.prisma ??
  new PrismaClient({
    log: process.env.NODE_ENV === 'development' ? ['error', 'warn'] : ['error'],
  })

if (process.env.NODE_ENV !== 'production') globalForPrisma.prisma = prisma
```

- [ ] **Step 4: Run migration**

```bash
npx prisma migrate dev --name init
```

Expected: migration succeeds, `prisma/migrations/` directory created.

- [ ] **Step 5: Generate Prisma client**

```bash
npx prisma generate
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add Prisma schema with all entities, run initial migration"
```

---

### Task 6: Plans config

**Files:**
- Create: `lib/plans.ts`
- Test: `__tests__/lib/plans.test.ts`

- [ ] **Step 1: Write tests**

Create `__tests__/lib/plans.test.ts`:

```typescript
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
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/lib/plans.test.ts
```

- [ ] **Step 3: Implement plans config**

Create `lib/plans.ts`:

```typescript
import { PlanTier } from '@prisma/client'

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
```

- [ ] **Step 4: Run — expect PASS**

```bash
npx jest __tests__/lib/plans.test.ts
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add plan config with limit and feature access enforcement"
```

---

### Task 7: NextAuth setup

**Files:**
- Create: `lib/auth.ts`
- Create: `app/api/auth/[...nextauth]/route.ts`
- Create: `lib/email.ts`
- Test: `__tests__/lib/auth.test.ts`

- [ ] **Step 1: Write auth utility tests**

Create `__tests__/lib/auth.test.ts`:

```typescript
import { hashPassword, verifyPassword } from '@/lib/auth'

test('hashPassword returns a bcrypt hash', async () => {
  const hash = await hashPassword('mypassword123')
  expect(hash).toMatch(/^\$2[aby]\$/)
})

test('verifyPassword returns true for correct password', async () => {
  const hash = await hashPassword('mypassword123')
  expect(await verifyPassword('mypassword123', hash)).toBe(true)
})

test('verifyPassword returns false for wrong password', async () => {
  const hash = await hashPassword('mypassword123')
  expect(await verifyPassword('wrongpassword', hash)).toBe(false)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/lib/auth.test.ts
```

- [ ] **Step 3: Create auth utilities and NextAuth config**

Create `lib/auth.ts`:

```typescript
import NextAuth, { NextAuthConfig } from 'next-auth'
import CredentialsProvider from 'next-auth/providers/credentials'
import DiscordProvider from 'next-auth/providers/discord'
import { PrismaAdapter } from '@auth/prisma-adapter'
import bcrypt from 'bcryptjs'
import { prisma } from '@/lib/prisma'
import { z } from 'zod'

export async function hashPassword(password: string): Promise<string> {
  return bcrypt.hash(password, 12)
}

export async function verifyPassword(password: string, hash: string): Promise<boolean> {
  return bcrypt.compare(password, hash)
}

const loginSchema = z.object({
  email: z.string().email(),
  password: z.string().min(1),
})

export const authConfig: NextAuthConfig = {
  adapter: PrismaAdapter(prisma),
  session: { strategy: 'jwt' },
  pages: {
    signIn: '/login',
    error: '/login',
  },
  providers: [
    CredentialsProvider({
      name: 'credentials',
      credentials: {
        email: { label: 'Email', type: 'email' },
        password: { label: 'Password', type: 'password' },
      },
      async authorize(credentials) {
        const parsed = loginSchema.safeParse(credentials)
        if (!parsed.success) return null

        const user = await prisma.user.findUnique({
          where: { email: parsed.data.email },
        })
        if (!user?.passwordHash) return null
        if (!user.emailVerified) return null

        const valid = await verifyPassword(parsed.data.password, user.passwordHash)
        if (!valid) return null

        return { id: user.id, email: user.email, image: user.avatar }
      },
    }),
    DiscordProvider({
      clientId: process.env.DISCORD_CLIENT_ID!,
      clientSecret: process.env.DISCORD_CLIENT_SECRET!,
      profile(profile) {
        return {
          id: profile.id,
          email: profile.email,
          name: profile.username,
          image: profile.avatar
            ? `https://cdn.discordapp.com/avatars/${profile.id}/${profile.avatar}.png`
            : null,
          discordId: profile.id,
          discordUsername: profile.username,
        }
      },
    }),
  ],
  callbacks: {
    async jwt({ token, user }) {
      if (user) {
        token.userId = user.id
        token.isSuperAdmin = (user as { isSuperAdmin?: boolean }).isSuperAdmin ?? false
      }
      return token
    },
    async session({ session, token }) {
      if (token) {
        session.user.id = token.userId as string
        session.user.isSuperAdmin = token.isSuperAdmin as boolean
      }
      return session
    },
    async signIn({ user, account }) {
      // For Discord OAuth — link discordId to existing user if email matches
      if (account?.provider === 'discord' && user.email) {
        const existing = await prisma.user.findUnique({ where: { email: user.email } })
        if (existing && !existing.discordId) {
          await prisma.user.update({
            where: { id: existing.id },
            data: {
              discordId: (user as { discordId?: string }).discordId,
              discordUsername: (user as { discordUsername?: string }).discordUsername,
              avatar: user.image ?? existing.avatar,
              emailVerified: existing.emailVerified ?? new Date(),
            },
          })
        }
      }
      return true
    },
  },
}

export const { handlers, auth, signIn, signOut } = NextAuth(authConfig)
```

- [ ] **Step 4: Create NextAuth route**

Create `app/api/auth/[...nextauth]/route.ts`:

```typescript
import { handlers } from '@/lib/auth'
export const { GET, POST } = handlers
```

- [ ] **Step 5: Create email utility**

Create `lib/email.ts`:

```typescript
import nodemailer from 'nodemailer'

const transporter = nodemailer.createTransport({
  host: process.env.SMTP_HOST,
  port: Number(process.env.SMTP_PORT),
  auth: {
    user: process.env.SMTP_USER,
    pass: process.env.SMTP_PASS,
  },
})

export async function sendEmail({
  to,
  subject,
  html,
}: {
  to: string
  subject: string
  html: string
}) {
  await transporter.sendMail({
    from: process.env.SMTP_FROM,
    to,
    subject,
    html,
  })
}

export function passwordResetEmailHtml(resetUrl: string): string {
  return `
    <div style="font-family:Inter,system-ui,sans-serif;max-width:480px;margin:0 auto;background:#0f0f0f;color:#fff;padding:32px;border-radius:8px;">
      <h2 style="font-size:18px;font-weight:600;margin-bottom:8px;">Reset your password</h2>
      <p style="font-size:14px;color:#888;margin-bottom:24px;">Click the link below to reset your password. This link expires in 1 hour.</p>
      <a href="${resetUrl}" style="display:inline-block;background:#fff;color:#000;font-size:13px;font-weight:600;padding:10px 20px;border-radius:6px;text-decoration:none;">Reset Password</a>
      <p style="font-size:12px;color:#444;margin-top:24px;">If you didn't request this, ignore this email.</p>
    </div>
  `
}

export function verifyEmailHtml(verifyUrl: string): string {
  return `
    <div style="font-family:Inter,system-ui,sans-serif;max-width:480px;margin:0 auto;background:#0f0f0f;color:#fff;padding:32px;border-radius:8px;">
      <h2 style="font-size:18px;font-weight:600;margin-bottom:8px;">Verify your email</h2>
      <p style="font-size:14px;color:#888;margin-bottom:24px;">Click below to verify your email and activate your account.</p>
      <a href="${verifyUrl}" style="display:inline-block;background:#fff;color:#000;font-size:13px;font-weight:600;padding:10px 20px;border-radius:6px;text-decoration:none;">Verify Email</a>
    </div>
  `
}
```

- [ ] **Step 6: Extend NextAuth types**

Create `types/index.ts`:

```typescript
import { DefaultSession } from 'next-auth'

declare module 'next-auth' {
  interface Session {
    user: DefaultSession['user'] & {
      id: string
      isSuperAdmin: boolean
    }
  }
}
```

- [ ] **Step 7: Run tests — expect PASS**

```bash
npx jest __tests__/lib/auth.test.ts
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: configure NextAuth with credentials + Discord OAuth providers"
```

---

### Task 8: Register and login pages

**Files:**
- Create: `app/(auth)/layout.tsx`
- Create: `app/(auth)/register/page.tsx`
- Create: `app/(auth)/login/page.tsx`
- Create: `app/api/auth/register/route.ts`

- [ ] **Step 1: Write register API route test**

Create `__tests__/api/register.test.ts`:

```typescript
import { POST } from '@/app/api/auth/register/route'
import { prisma } from '@/lib/prisma'

jest.mock('@/lib/prisma', () => ({
  prisma: {
    user: {
      findUnique: jest.fn(),
      create: jest.fn(),
    },
  },
}))
jest.mock('@/lib/email', () => ({ sendEmail: jest.fn(), verifyEmailHtml: jest.fn(() => '') }))

const mockPrisma = prisma as jest.Mocked<typeof prisma>

beforeEach(() => jest.clearAllMocks())

test('returns 400 when email already exists', async () => {
  (mockPrisma.user.findUnique as jest.Mock).mockResolvedValue({ id: '1' })
  const req = new Request('http://localhost/api/auth/register', {
    method: 'POST',
    body: JSON.stringify({ email: 'test@test.com', password: 'password123', name: 'Test' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(400)
  const json = await res.json()
  expect(json.error).toMatch(/already/i)
})

test('returns 201 on successful registration', async () => {
  (mockPrisma.user.findUnique as jest.Mock).mockResolvedValue(null)
  ;(mockPrisma.user.create as jest.Mock).mockResolvedValue({ id: 'new-user', email: 'test@test.com' })
  const req = new Request('http://localhost/api/auth/register', {
    method: 'POST',
    body: JSON.stringify({ email: 'new@test.com', password: 'password123', name: 'Test' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(201)
})

test('returns 400 for invalid email', async () => {
  const req = new Request('http://localhost/api/auth/register', {
    method: 'POST',
    body: JSON.stringify({ email: 'not-an-email', password: 'password123', name: 'Test' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(400)
})

test('returns 400 for password under 8 chars', async () => {
  const req = new Request('http://localhost/api/auth/register', {
    method: 'POST',
    body: JSON.stringify({ email: 'test@test.com', password: 'short', name: 'Test' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(400)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/register.test.ts
```

- [ ] **Step 3: Implement register API route**

Create `app/api/auth/register/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { prisma } from '@/lib/prisma'
import { hashPassword } from '@/lib/auth'
import { sendEmail, verifyEmailHtml } from '@/lib/email'
import { nanoid } from 'nanoid'

const registerSchema = z.object({
  email: z.string().email(),
  password: z.string().min(8, 'Password must be at least 8 characters'),
  name: z.string().min(1),
})

export async function POST(req: Request) {
  try {
    const body = await req.json()
    const parsed = registerSchema.safeParse(body)
    if (!parsed.success) {
      return NextResponse.json(
        { error: parsed.error.errors[0].message },
        { status: 400 }
      )
    }

    const { email, password, name } = parsed.data

    const existing = await prisma.user.findUnique({ where: { email } })
    if (existing) {
      return NextResponse.json({ error: 'Email already in use' }, { status: 400 })
    }

    const passwordHash = await hashPassword(password)
    const verifyToken = nanoid(32)
    const expires = new Date(Date.now() + 24 * 60 * 60 * 1000) // 24h

    const user = await prisma.user.create({
      data: { email, passwordHash, avatar: null },
    })

    await prisma.verificationToken.create({
      data: { identifier: email, token: verifyToken, expires },
    })

    const verifyUrl = `${process.env.NEXT_PUBLIC_APP_URL}/api/auth/verify-email?token=${verifyToken}&email=${encodeURIComponent(email)}`
    await sendEmail({ to: email, subject: 'Verify your email', html: verifyEmailHtml(verifyUrl) })

    return NextResponse.json({ message: 'Check your email to verify your account' }, { status: 201 })
  } catch {
    return NextResponse.json({ error: 'Something went wrong' }, { status: 500 })
  }
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
npx jest __tests__/api/register.test.ts
```

- [ ] **Step 5: Create auth layout**

Create `app/(auth)/layout.tsx`:

```typescript
import { ReactNode } from 'react'

export default function AuthLayout({ children }: { children: ReactNode }) {
  return (
    <div className="min-h-screen bg-bg-base flex items-center justify-center p-4">
      <div className="w-full max-w-sm">
        <div className="flex items-center gap-2.5 justify-center mb-8">
          <div className="w-7 h-7 rounded-lg bg-white" />
          <span className="text-sm font-semibold tracking-tight">CommunityOS</span>
        </div>
        {children}
      </div>
    </div>
  )
}
```

- [ ] **Step 6: Create register page**

Create `app/(auth)/register/page.tsx`:

```typescript
'use client'
import { useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Card } from '@/components/ui/Card'

const schema = z.object({
  email: z.string().email('Invalid email'),
  password: z.string().min(8, 'At least 8 characters'),
  name: z.string().min(1, 'Name is required'),
})
type FormData = z.infer<typeof schema>

export default function RegisterPage() {
  const router = useRouter()
  const [serverError, setServerError] = useState('')
  const [success, setSuccess] = useState(false)

  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<FormData>({
    resolver: zodResolver(schema),
  })

  async function onSubmit(data: FormData) {
    setServerError('')
    const res = await fetch('/api/auth/register', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    })
    const json = await res.json()
    if (!res.ok) { setServerError(json.error); return }
    setSuccess(true)
  }

  if (success) {
    return (
      <Card>
        <h1 className="text-lg font-semibold mb-1">Check your email</h1>
        <p className="text-sm text-text-muted">We sent a verification link to your email address.</p>
      </Card>
    )
  }

  return (
    <Card>
      <h1 className="text-lg font-semibold mb-1">Create an account</h1>
      <p className="text-sm text-text-muted mb-6">Start managing your community</p>

      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input label="Name" id="name" placeholder="Your name" error={errors.name?.message} {...register('name')} />
        <Input label="Email" id="email" type="email" placeholder="you@example.com" error={errors.email?.message} {...register('email')} />
        <Input label="Password" id="password" type="password" placeholder="Min 8 characters" error={errors.password?.message} {...register('password')} />
        {serverError && <p className="text-xs text-danger">{serverError}</p>}
        <Button type="submit" loading={isSubmitting} className="w-full mt-1">Create account</Button>
      </form>

      <p className="text-xs text-text-muted text-center mt-4">
        Already have an account?{' '}
        <Link href="/login" className="text-text-secondary hover:text-text-primary">Sign in</Link>
      </p>
    </Card>
  )
}
```

- [ ] **Step 7: Create login page**

Create `app/(auth)/login/page.tsx`:

```typescript
'use client'
import { useState } from 'react'
import Link from 'next/link'
import { signIn } from 'next-auth/react'
import { useRouter, useSearchParams } from 'next/navigation'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Card } from '@/components/ui/Card'

const schema = z.object({
  email: z.string().email('Invalid email'),
  password: z.string().min(1, 'Password required'),
})
type FormData = z.infer<typeof schema>

export default function LoginPage() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const [error, setError] = useState(searchParams.get('error') ? 'Invalid credentials' : '')

  const { register, handleSubmit, formState: { errors, isSubmitting } } = useForm<FormData>({
    resolver: zodResolver(schema),
  })

  async function onSubmit(data: FormData) {
    setError('')
    const result = await signIn('credentials', {
      email: data.email,
      password: data.password,
      redirect: false,
    })
    if (result?.error) { setError('Invalid email or password'); return }
    router.push('/dashboard')
    router.refresh()
  }

  async function onDiscordLogin() {
    await signIn('discord', { callbackUrl: '/dashboard' })
  }

  return (
    <Card>
      <h1 className="text-lg font-semibold mb-1">Welcome back</h1>
      <p className="text-sm text-text-muted mb-6">Sign in to your account</p>

      <button
        onClick={onDiscordLogin}
        className="w-full flex items-center justify-center gap-2 bg-[#5865F2] hover:bg-[#4752c4] text-white text-sm font-medium py-2 px-4 rounded transition-colors mb-4"
      >
        <svg width="16" height="12" viewBox="0 0 24 18" fill="currentColor">
          <path d="M20.317 1.492c-1.53-.69-3.17-1.2-4.885-1.49a.075.075 0 0 0-.079.036c-.21.369-.444.85-.608 1.23a18.566 18.566 0 0 0-5.487 0 12.36 12.36 0 0 0-.617-1.23.077.077 0 0 0-.079-.036A19.496 19.496 0 0 0 3.677 1.492a.07.07 0 0 0-.032.027C.533 6.093-.32 10.555.099 14.961a.08.08 0 0 0 .031.055 19.9 19.9 0 0 0 5.993 2.98.078.078 0 0 0 .084-.026c.462-.62.874-1.275 1.226-1.963a.075.075 0 0 0-.041-.104 13.107 13.107 0 0 1-1.872-.878.075.075 0 0 1-.008-.125c.126-.093.252-.19.372-.287a.075.075 0 0 1 .078-.01c3.927 1.764 8.18 1.764 12.061 0a.075.075 0 0 1 .079.009c.12.098.245.195.372.288a.075.075 0 0 1-.006.125c-.598.344-1.22.635-1.873.877a.075.075 0 0 0-.041.105c.36.687.772 1.341 1.225 1.962a.077.077 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-2.981.076.076 0 0 0 .032-.054c.5-5.094-.838-9.52-3.549-13.442a.06.06 0 0 0-.031-.028z"/>
        </svg>
        Continue with Discord
      </button>

      <div className="flex items-center gap-3 mb-4">
        <div className="flex-1 h-px bg-border-default" />
        <span className="text-xs text-text-faint">or</span>
        <div className="flex-1 h-px bg-border-default" />
      </div>

      <form onSubmit={handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Input label="Email" id="email" type="email" placeholder="you@example.com" error={errors.email?.message} {...register('email')} />
        <Input label="Password" id="password" type="password" placeholder="Your password" error={errors.password?.message} {...register('password')} />
        {error && <p className="text-xs text-danger">{error}</p>}
        <Button type="submit" loading={isSubmitting} className="w-full">Sign in</Button>
      </form>

      <div className="flex justify-between mt-4">
        <Link href="/forgot-password" className="text-xs text-text-muted hover:text-text-secondary">Forgot password?</Link>
        <Link href="/register" className="text-xs text-text-muted hover:text-text-secondary">Create account</Link>
      </div>
    </Card>
  )
}
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add register and login pages with email/password + Discord OAuth"
```

---

### Task 9: Password reset flow + email verification

**Files:**
- Create: `app/api/auth/forgot-password/route.ts`
- Create: `app/api/auth/reset-password/route.ts`
- Create: `app/api/auth/verify-email/route.ts`
- Create: `app/(auth)/forgot-password/page.tsx`
- Create: `app/(auth)/reset-password/page.tsx`

- [ ] **Step 1: Write API tests**

Create `__tests__/api/password-reset.test.ts`:

```typescript
import { POST as forgotPost } from '@/app/api/auth/forgot-password/route'
import { POST as resetPost } from '@/app/api/auth/reset-password/route'
import { prisma } from '@/lib/prisma'

jest.mock('@/lib/prisma', () => ({
  prisma: {
    user: { findUnique: jest.fn() },
    verificationToken: {
      create: jest.fn(),
      findUnique: jest.fn(),
      delete: jest.fn(),
    },
  },
}))
jest.mock('@/lib/email', () => ({ sendEmail: jest.fn(), passwordResetEmailHtml: jest.fn(() => '') }))
jest.mock('@/lib/auth', () => ({
  ...jest.requireActual('@/lib/auth'),
  hashPassword: jest.fn(async (p: string) => `hashed:${p}`),
}))

const mock = prisma as jest.Mocked<typeof prisma>

beforeEach(() => jest.clearAllMocks())

test('forgot-password returns 200 even if user not found (no enumeration)', async () => {
  (mock.user.findUnique as jest.Mock).mockResolvedValue(null)
  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ email: 'notfound@test.com' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await forgotPost(req)
  expect(res.status).toBe(200)
})

test('reset-password returns 400 for expired token', async () => {
  ;(mock.verificationToken.findUnique as jest.Mock).mockResolvedValue({
    identifier: 'test@test.com',
    token: 'tok',
    expires: new Date(Date.now() - 1000),
  })
  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ token: 'tok', email: 'test@test.com', password: 'newpassword123' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await resetPost(req)
  expect(res.status).toBe(400)
  const json = await res.json()
  expect(json.error).toMatch(/expired/i)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/password-reset.test.ts
```

- [ ] **Step 3: Implement forgot-password route**

Create `app/api/auth/forgot-password/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { prisma } from '@/lib/prisma'
import { sendEmail, passwordResetEmailHtml } from '@/lib/email'
import { nanoid } from 'nanoid'

export async function POST(req: Request) {
  try {
    const { email } = z.object({ email: z.string().email() }).parse(await req.json())
    const user = await prisma.user.findUnique({ where: { email } })

    // Always return 200 to prevent email enumeration
    if (!user) return NextResponse.json({ message: 'If that email exists, a reset link was sent.' })

    const token = nanoid(32)
    const expires = new Date(Date.now() + 60 * 60 * 1000) // 1h

    await prisma.verificationToken.create({ data: { identifier: email, token, expires } })

    const resetUrl = `${process.env.NEXT_PUBLIC_APP_URL}/reset-password?token=${token}&email=${encodeURIComponent(email)}`
    await sendEmail({ to: email, subject: 'Reset your password', html: passwordResetEmailHtml(resetUrl) })

    return NextResponse.json({ message: 'If that email exists, a reset link was sent.' })
  } catch {
    return NextResponse.json({ error: 'Something went wrong' }, { status: 500 })
  }
}
```

- [ ] **Step 4: Implement reset-password route**

Create `app/api/auth/reset-password/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { prisma } from '@/lib/prisma'
import { hashPassword } from '@/lib/auth'

const schema = z.object({
  token: z.string(),
  email: z.string().email(),
  password: z.string().min(8),
})

export async function POST(req: Request) {
  try {
    const { token, email, password } = schema.parse(await req.json())

    const record = await prisma.verificationToken.findUnique({ where: { token } })
    if (!record || record.identifier !== email) {
      return NextResponse.json({ error: 'Invalid or expired reset link' }, { status: 400 })
    }
    if (record.expires < new Date()) {
      await prisma.verificationToken.delete({ where: { token } })
      return NextResponse.json({ error: 'Reset link has expired' }, { status: 400 })
    }

    const passwordHash = await hashPassword(password)
    await prisma.user.update({ where: { email }, data: { passwordHash } })
    await prisma.verificationToken.delete({ where: { token } })

    return NextResponse.json({ message: 'Password updated successfully' })
  } catch {
    return NextResponse.json({ error: 'Something went wrong' }, { status: 500 })
  }
}
```

- [ ] **Step 5: Implement verify-email route**

Create `app/api/auth/verify-email/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { prisma } from '@/lib/prisma'

export async function GET(req: Request) {
  const { searchParams } = new URL(req.url)
  const token = searchParams.get('token')
  const email = searchParams.get('email')

  if (!token || !email) return NextResponse.redirect(`${process.env.NEXT_PUBLIC_APP_URL}/login?error=invalid`)

  const record = await prisma.verificationToken.findUnique({ where: { token } })
  if (!record || record.identifier !== email || record.expires < new Date()) {
    return NextResponse.redirect(`${process.env.NEXT_PUBLIC_APP_URL}/login?error=expired`)
  }

  await prisma.user.update({ where: { email }, data: { emailVerified: new Date() } })
  await prisma.verificationToken.delete({ where: { token } })

  return NextResponse.redirect(`${process.env.NEXT_PUBLIC_APP_URL}/login?verified=1`)
}
```

- [ ] **Step 6: Create forgot/reset password pages**

Create `app/(auth)/forgot-password/page.tsx`:

```typescript
'use client'
import { useState } from 'react'
import Link from 'next/link'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Card } from '@/components/ui/Card'

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [loading, setLoading] = useState(false)
  const [sent, setSent] = useState(false)

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    setLoading(true)
    await fetch('/api/auth/forgot-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email }),
    })
    setSent(true)
    setLoading(false)
  }

  if (sent) return (
    <Card>
      <h1 className="text-lg font-semibold mb-1">Check your email</h1>
      <p className="text-sm text-text-muted">If an account exists, a reset link was sent.</p>
      <Link href="/login" className="text-xs text-text-muted hover:text-text-secondary mt-4 inline-block">Back to login</Link>
    </Card>
  )

  return (
    <Card>
      <h1 className="text-lg font-semibold mb-1">Reset password</h1>
      <p className="text-sm text-text-muted mb-6">Enter your email to receive a reset link</p>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input label="Email" id="email" type="email" value={email} onChange={e => setEmail(e.target.value)} placeholder="you@example.com" />
        <Button type="submit" loading={loading} className="w-full">Send reset link</Button>
      </form>
      <Link href="/login" className="text-xs text-text-muted hover:text-text-secondary mt-4 inline-block">Back to login</Link>
    </Card>
  )
}
```

Create `app/(auth)/reset-password/page.tsx`:

```typescript
'use client'
import { useState } from 'react'
import { useSearchParams, useRouter } from 'next/navigation'
import Link from 'next/link'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Card } from '@/components/ui/Card'

export default function ResetPasswordPage() {
  const router = useRouter()
  const params = useSearchParams()
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (password.length < 8) { setError('Password must be at least 8 characters'); return }
    setLoading(true)
    const res = await fetch('/api/auth/reset-password', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ token: params.get('token'), email: params.get('email'), password }),
    })
    const json = await res.json()
    if (!res.ok) { setError(json.error); setLoading(false); return }
    router.push('/login?reset=1')
  }

  return (
    <Card>
      <h1 className="text-lg font-semibold mb-1">Set new password</h1>
      <p className="text-sm text-text-muted mb-6">Choose a new password for your account</p>
      <form onSubmit={onSubmit} className="flex flex-col gap-4">
        <Input label="New password" id="password" type="password" value={password} onChange={e => setPassword(e.target.value)} placeholder="Min 8 characters" />
        {error && <p className="text-xs text-danger">{error}</p>}
        <Button type="submit" loading={loading} className="w-full">Update password</Button>
      </form>
    </Card>
  )
}
```

- [ ] **Step 7: Run tests — expect PASS**

```bash
npx jest __tests__/api/password-reset.test.ts
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add password reset flow and email verification"
```

---

### Task 10: 2FA (TOTP)

**Files:**
- Create: `lib/totp.ts`
- Create: `app/api/auth/2fa/setup/route.ts`
- Create: `app/api/auth/2fa/verify/route.ts`
- Create: `app/api/auth/2fa/disable/route.ts`
- Test: `__tests__/lib/totp.test.ts`

- [ ] **Step 1: Write TOTP tests**

Create `__tests__/lib/totp.test.ts`:

```typescript
import { generateTotpSecret, verifyTotpToken } from '@/lib/totp'
import speakeasy from 'speakeasy'

test('generateTotpSecret returns base32 secret and otpauth URL', () => {
  const result = generateTotpSecret('test@example.com')
  expect(result.secret).toBeTruthy()
  expect(result.otpauthUrl).toContain('otpauth://totp/')
  expect(result.otpauthUrl).toContain('CommunityOS')
})

test('verifyTotpToken returns true for valid token', () => {
  const { secret } = generateTotpSecret('test@example.com')
  const token = speakeasy.totp({ secret, encoding: 'base32' })
  expect(verifyTotpToken(secret, token)).toBe(true)
})

test('verifyTotpToken returns false for wrong token', () => {
  const { secret } = generateTotpSecret('test@example.com')
  expect(verifyTotpToken(secret, '000000')).toBe(false)
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/lib/totp.test.ts
```

- [ ] **Step 3: Implement TOTP utility**

Create `lib/totp.ts`:

```typescript
import speakeasy from 'speakeasy'

export function generateTotpSecret(email: string) {
  const secret = speakeasy.generateSecret({
    name: `CommunityOS (${email})`,
    issuer: 'CommunityOS',
    length: 20,
  })
  return {
    secret: secret.base32,
    otpauthUrl: secret.otpauth_url ?? '',
  }
}

export function verifyTotpToken(secret: string, token: string): boolean {
  return speakeasy.totp.verify({
    secret,
    encoding: 'base32',
    token,
    window: 1,
  })
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
npx jest __tests__/lib/totp.test.ts
```

- [ ] **Step 5: Implement 2FA API routes**

Create `app/api/auth/2fa/setup/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { generateTotpSecret } from '@/lib/totp'
import QRCode from 'qrcode'

export async function POST() {
  const session = await auth()
  if (!session?.user?.id) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })

  const { secret, otpauthUrl } = generateTotpSecret(session.user.email!)

  // Store temp secret (not enabled until verified)
  await prisma.user.update({
    where: { id: session.user.id },
    data: { totpSecret: secret },
  })

  const qrCodeDataUrl = await QRCode.toDataURL(otpauthUrl)
  return NextResponse.json({ qrCode: qrCodeDataUrl, secret })
}
```

Create `app/api/auth/2fa/verify/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { verifyTotpToken } from '@/lib/totp'

export async function POST(req: Request) {
  const session = await auth()
  if (!session?.user?.id) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })

  const { token } = z.object({ token: z.string().length(6) }).parse(await req.json())

  const user = await prisma.user.findUnique({ where: { id: session.user.id } })
  if (!user?.totpSecret) return NextResponse.json({ error: 'No 2FA setup in progress' }, { status: 400 })

  const valid = verifyTotpToken(user.totpSecret, token)
  if (!valid) return NextResponse.json({ error: 'Invalid code' }, { status: 400 })

  await prisma.user.update({ where: { id: user.id }, data: { totpEnabled: true } })
  return NextResponse.json({ message: '2FA enabled' })
}
```

Create `app/api/auth/2fa/disable/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { verifyTotpToken } from '@/lib/totp'

export async function POST(req: Request) {
  const session = await auth()
  if (!session?.user?.id) return NextResponse.json({ error: 'Unauthorized' }, { status: 401 })

  const { token } = z.object({ token: z.string().length(6) }).parse(await req.json())

  const user = await prisma.user.findUnique({ where: { id: session.user.id } })
  if (!user?.totpEnabled || !user.totpSecret) {
    return NextResponse.json({ error: '2FA is not enabled' }, { status: 400 })
  }

  const valid = verifyTotpToken(user.totpSecret, token)
  if (!valid) return NextResponse.json({ error: 'Invalid code' }, { status: 400 })

  await prisma.user.update({ where: { id: user.id }, data: { totpEnabled: false, totpSecret: null } })
  return NextResponse.json({ message: '2FA disabled' })
}
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: implement TOTP 2FA setup, verify, and disable"
```

---

### Task 11: Middleware — auth + community validation

**Files:**
- Create: `middleware.ts`
- Test: `__tests__/middleware.test.ts`

- [ ] **Step 1: Write middleware tests**

Create `__tests__/middleware.test.ts`:

```typescript
import { middleware } from '@/middleware'
import { NextRequest } from 'next/server'

// Mock next-auth
jest.mock('@/lib/auth', () => ({
  auth: jest.fn(),
}))

import { auth } from '@/lib/auth'
const mockAuth = auth as jest.Mock

function makeReq(pathname: string) {
  return new NextRequest(`http://localhost${pathname}`)
}

test('redirects unauthenticated user from /dashboard to /login', async () => {
  mockAuth.mockResolvedValue(null)
  const res = await middleware(makeReq('/dashboard'))
  expect(res.status).toBe(307)
  expect(res.headers.get('location')).toContain('/login')
})

test('allows unauthenticated access to /login', async () => {
  mockAuth.mockResolvedValue(null)
  const res = await middleware(makeReq('/login'))
  // next() — no redirect
  expect(res.status).not.toBe(307)
})

test('redirects authenticated user away from /login to /dashboard', async () => {
  mockAuth.mockResolvedValue({ user: { id: '1' } })
  const res = await middleware(makeReq('/login'))
  expect(res.status).toBe(307)
  expect(res.headers.get('location')).toContain('/dashboard')
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/middleware.test.ts
```

- [ ] **Step 3: Implement middleware**

Create `middleware.ts`:

```typescript
import { NextRequest, NextResponse } from 'next/server'
import { auth } from '@/lib/auth'

const PUBLIC_PATHS = ['/login', '/register', '/forgot-password', '/reset-password', '/api/auth', '/api/webhooks']
const AUTH_PATHS = ['/login', '/register', '/forgot-password', '/reset-password']
const SUPERADMIN_PATH = '/superadmin'

export async function middleware(req: NextRequest) {
  const { pathname } = req.nextUrl
  const session = await auth()

  const isPublic = PUBLIC_PATHS.some(p => pathname.startsWith(p))
  const isAuthPage = AUTH_PATHS.some(p => pathname.startsWith(p))

  // Unauthenticated user hitting protected route
  if (!session && !isPublic) {
    const loginUrl = new URL('/login', req.url)
    loginUrl.searchParams.set('callbackUrl', pathname)
    return NextResponse.redirect(loginUrl)
  }

  // Authenticated user hitting auth pages — send to dashboard
  if (session && isAuthPage) {
    return NextResponse.redirect(new URL('/dashboard', req.url))
  }

  // Super admin guard
  if (pathname.startsWith(SUPERADMIN_PATH) && !session?.user?.isSuperAdmin) {
    return NextResponse.redirect(new URL('/dashboard', req.url))
  }

  return NextResponse.next()
}

export const config = {
  matcher: ['/((?!_next/static|_next/image|favicon.ico|.*\\.png$).*)'],
}
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
npx jest __tests__/middleware.test.ts
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add middleware for auth protection and super admin guard"
```

---

### Task 12: Stripe setup and plans config

**Files:**
- Create: `lib/stripe.ts`
- Test: `__tests__/lib/stripe.test.ts`

- [ ] **Step 1: Write Stripe utility tests**

Create `__tests__/lib/stripe.test.ts`:

```typescript
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
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/lib/stripe.test.ts
```

- [ ] **Step 3: Implement Stripe client**

Create `lib/stripe.ts`:

```typescript
import Stripe from 'stripe'
import { PlanTier } from '@prisma/client'

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
```

- [ ] **Step 4: Run tests — expect PASS**

```bash
npx jest __tests__/lib/stripe.test.ts
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Stripe client with checkout session creation"
```

---

### Task 13: Onboarding page and community creation

**Files:**
- Create: `app/onboarding/page.tsx`
- Create: `app/api/community/create/route.ts`
- Create: `app/api/webhooks/stripe/route.ts`
- Test: `__tests__/api/community-create.test.ts`

- [ ] **Step 1: Write community creation tests**

Create `__tests__/api/community-create.test.ts`:

```typescript
import { POST } from '@/app/api/community/create/route'

jest.mock('@/lib/auth', () => ({ auth: jest.fn() }))
jest.mock('@/lib/prisma', () => ({
  prisma: {
    community: { findFirst: jest.fn(), create: jest.fn() },
    communityMember: { create: jest.fn() },
  },
}))
jest.mock('@/lib/stripe', () => ({
  createCheckoutSession: jest.fn(async () => 'https://checkout.stripe.com/pay/cs_test'),
  getPriceIdForPlan: jest.fn(() => 'price_123'),
}))

import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'

const mockAuth = auth as jest.Mock
const mockPrisma = prisma as jest.Mocked<typeof prisma>

beforeEach(() => jest.clearAllMocks())

test('returns 401 if not authenticated', async () => {
  mockAuth.mockResolvedValue(null)
  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ name: 'My Community', planTier: 'FREE' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(401)
})

test('returns 400 for missing name', async () => {
  mockAuth.mockResolvedValue({ user: { id: '1', email: 'a@b.com' } })
  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ name: '', planTier: 'FREE' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(400)
})

test('creates FREE community and returns it directly', async () => {
  mockAuth.mockResolvedValue({ user: { id: '1', email: 'a@b.com' } })
  ;(mockPrisma.community.findFirst as jest.Mock).mockResolvedValue(null)
  ;(mockPrisma.community.create as jest.Mock).mockResolvedValue({ id: 'comm-1', name: 'Test', slug: 'test' })
  ;(mockPrisma.communityMember.create as jest.Mock).mockResolvedValue({})

  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ name: 'Test Community', planTier: 'FREE' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(201)
  const json = await res.json()
  expect(json.community).toBeDefined()
})

test('returns Stripe checkout URL for paid plans', async () => {
  mockAuth.mockResolvedValue({ user: { id: '1', email: 'a@b.com' } })
  ;(mockPrisma.community.findFirst as jest.Mock).mockResolvedValue(null)

  const req = new Request('http://localhost', {
    method: 'POST',
    body: JSON.stringify({ name: 'Test Community', planTier: 'STANDARD' }),
    headers: { 'Content-Type': 'application/json' },
  })
  const res = await POST(req)
  expect(res.status).toBe(200)
  const json = await res.json()
  expect(json.checkoutUrl).toContain('stripe.com')
})
```

- [ ] **Step 2: Run — expect FAIL**

```bash
npx jest __tests__/api/community-create.test.ts
```

- [ ] **Step 3: Implement community create route**

Create `app/api/community/create/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { z } from 'zod'
import { auth } from '@/lib/auth'
import { prisma } from '@/lib/prisma'
import { createCheckoutSession } from '@/lib/stripe'
import { PlanTier } from '@prisma/client'

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
      return NextResponse.json({ error: parsed.error.errors[0].message }, { status: 400 })
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
    // Community is created by webhook after payment
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
```

- [ ] **Step 4: Implement Stripe webhook**

Create `app/api/webhooks/stripe/route.ts`:

```typescript
import { NextResponse } from 'next/server'
import { stripe } from '@/lib/stripe'
import { prisma } from '@/lib/prisma'
import { PlanTier } from '@prisma/client'
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
```

- [ ] **Step 5: Run tests — expect PASS**

```bash
npx jest __tests__/api/community-create.test.ts
```

- [ ] **Step 6: Create onboarding page**

Create `app/onboarding/page.tsx`:

```typescript
'use client'
import { useState } from 'react'
import { useRouter, useSearchParams } from 'next/navigation'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/ui/Input'
import { Card, CardTitle } from '@/components/ui/Card'
import { cn } from '@/lib/utils'

type PlanTier = 'FREE' | 'STANDARD' | 'PRO'

const plans = [
  { tier: 'FREE' as PlanTier,     label: 'Free',     price: '$0/mo',   desc: 'Up to 15 members, 1 department' },
  { tier: 'STANDARD' as PlanTier, label: 'Standard', price: '$9/mo',   desc: 'Up to 75 members, 5 departments' },
  { tier: 'PRO' as PlanTier,      label: 'Pro',       price: '$19/mo',  desc: 'Unlimited members + all features' },
]

export default function OnboardingPage() {
  const router = useRouter()
  const params = useSearchParams()
  const success = params.get('success')

  const [name, setName] = useState('')
  const [plan, setPlan] = useState<PlanTier>('FREE')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')

  if (success) {
    return (
      <div className="min-h-screen bg-bg-base flex items-center justify-center p-4">
        <Card className="max-w-sm w-full text-center">
          <div className="w-10 h-10 rounded-full bg-success-bg border border-success/30 flex items-center justify-center mx-auto mb-4">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18" className="text-success">
              <path d="M20 6L9 17l-5-5" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
            </svg>
          </div>
          <h1 className="text-lg font-semibold mb-1">Community created!</h1>
          <p className="text-sm text-text-muted mb-5">Your workspace is ready.</p>
          <Button onClick={() => router.push('/dashboard')} className="w-full">Go to Dashboard</Button>
        </Card>
      </div>
    )
  }

  async function onSubmit(e: React.FormEvent) {
    e.preventDefault()
    if (!name.trim()) { setError('Community name is required'); return }
    setLoading(true)
    setError('')

    const res = await fetch('/api/community/create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name, planTier: plan }),
    })
    const json = await res.json()

    if (!res.ok) { setError(json.error); setLoading(false); return }

    if (json.checkoutUrl) {
      window.location.href = json.checkoutUrl
    } else {
      router.push('/dashboard')
    }
  }

  return (
    <div className="min-h-screen bg-bg-base flex items-center justify-center p-4">
      <div className="w-full max-w-lg">
        <div className="flex items-center gap-2.5 justify-center mb-8">
          <div className="w-7 h-7 rounded-lg bg-white" />
          <span className="text-sm font-semibold tracking-tight">CommunityOS</span>
        </div>

        <h1 className="text-xl font-semibold text-center mb-1 tracking-tight">Create your community</h1>
        <p className="text-sm text-text-muted text-center mb-8">Set up your workspace in under a minute</p>

        <form onSubmit={onSubmit} className="flex flex-col gap-5">
          <Input label="Community Name" id="name" value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Los Santos Police Department" />

          <div>
            <p className="text-xs text-text-muted uppercase tracking-wider mb-2">Choose a plan</p>
            <div className="flex flex-col gap-2">
              {plans.map(p => (
                <button
                  key={p.tier}
                  type="button"
                  onClick={() => setPlan(p.tier)}
                  className={cn(
                    'flex items-center justify-between p-3.5 rounded border text-left transition-colors',
                    plan === p.tier
                      ? 'border-white bg-bg-elevated'
                      : 'border-border-default hover:border-border-light'
                  )}
                >
                  <div>
                    <div className="flex items-center gap-2">
                      <span className="text-sm font-medium text-text-primary">{p.label}</span>
                      {p.tier === 'PRO' && (
                        <span className="text-[10px] bg-bg-elevated border border-border-light text-text-muted px-1.5 py-0.5 rounded">Popular</span>
                      )}
                    </div>
                    <p className="text-xs text-text-muted mt-0.5">{p.desc}</p>
                  </div>
                  <span className="text-sm font-medium text-text-secondary">{p.price}</span>
                </button>
              ))}
            </div>
          </div>

          {error && <p className="text-xs text-danger">{error}</p>}

          <Button type="submit" loading={loading} className="w-full">
            {plan === 'FREE' ? 'Create Community' : 'Continue to Payment'}
          </Button>
        </form>
      </div>
    </div>
  )
}
```

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add onboarding page, community creation API, Stripe webhook handler"
```

---

### Task 14: Final wiring — root layout, SessionProvider, run all tests

**Files:**
- Modify: `app/layout.tsx`
- Create: `components/providers/SessionProvider.tsx`

- [ ] **Step 1: Create SessionProvider wrapper**

Create `components/providers/SessionProvider.tsx`:

```typescript
'use client'
import { SessionProvider as NextAuthSessionProvider } from 'next-auth/react'
import { ReactNode } from 'react'

export function SessionProvider({ children }: { children: ReactNode }) {
  return <NextAuthSessionProvider>{children}</NextAuthSessionProvider>
}
```

- [ ] **Step 2: Update root layout**

Replace `app/layout.tsx`:

```typescript
import type { Metadata } from 'next'
import { SessionProvider } from '@/components/providers/SessionProvider'
import './globals.css'

export const metadata: Metadata = {
  title: 'CommunityOS',
  description: 'FiveM Community Management Platform',
}

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <SessionProvider>
          {children}
        </SessionProvider>
      </body>
    </html>
  )
}
```

- [ ] **Step 3: Run full test suite**

```bash
npx jest --passWithNoTests
```

Expected: all tests pass.

- [ ] **Step 4: Verify dev server starts**

```bash
npm run dev
```

Expected: server starts on port 3000, no TypeScript errors. Visit `http://localhost:3000/login` — login page renders with the monochrome design.

- [ ] **Step 5: Final commit**

```bash
git add -A
git commit -m "feat: wire root layout with SessionProvider — Phase 1 complete"
```

---

## Phase 1 Complete

At this point you have:
- Next.js 14 app scaffolded and running
- Full monochrome design system (Tailwind tokens, UI primitives, AppShell layout)
- Complete Prisma schema with all entities, migrated to MySQL
- NextAuth with email/password + Discord OAuth
- Email verification, password reset
- TOTP 2FA (setup, verify, disable)
- Middleware protecting all app routes
- Onboarding page with plan selection
- Community creation (FREE instant, paid via Stripe Checkout)
- Stripe webhook handling (subscription created/updated/cancelled/failed)
- Plan config with limit + feature access enforcement

**Next:** Phase 2 — community context switcher, all-tier feature pages (dashboard, announcements, events, messages, roster), admin application review.
