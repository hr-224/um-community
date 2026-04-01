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
