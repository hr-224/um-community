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
