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
