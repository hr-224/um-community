import fs from 'fs'
import path from 'path'

const css = fs.readFileSync(path.join(process.cwd(), 'app/globals.css'), 'utf-8')

test('globals.css defines bg-base token', () => {
  expect(css).toContain('--color-bg-base')
  expect(css).toContain('#0a0a0a')
})

test('globals.css defines bg-surface token', () => {
  expect(css).toContain('--color-bg-surface')
  expect(css).toContain('#0f0f0f')
})

test('globals.css defines bg-elevated token', () => {
  expect(css).toContain('--color-bg-elevated')
  expect(css).toContain('#141414')
})

test('globals.css defines border-default token', () => {
  expect(css).toContain('--color-border-default')
  expect(css).toContain('#1c1c1c')
})

test('globals.css defines text-primary token', () => {
  expect(css).toContain('--color-text-primary')
  expect(css).toContain('#ffffff')
})
