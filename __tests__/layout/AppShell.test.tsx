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
