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
