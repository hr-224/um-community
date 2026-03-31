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
