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
