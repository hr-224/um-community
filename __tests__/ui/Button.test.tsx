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
