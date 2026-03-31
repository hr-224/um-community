import { forwardRef, ButtonHTMLAttributes } from 'react'
import { cn } from '@/lib/utils'
import { Spinner } from '@/components/ui/Spinner'

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
        {loading && <Spinner className="h-3.5 w-3.5" />}
        {children}
      </button>
    )
  }
)
Button.displayName = 'Button'
