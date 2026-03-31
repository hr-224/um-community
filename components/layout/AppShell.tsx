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
