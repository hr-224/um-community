import '@testing-library/jest-dom'

// Polyfill TextEncoder/TextDecoder for packages that need them in jsdom
import { TextEncoder, TextDecoder } from 'util'
if (typeof globalThis.TextEncoder === 'undefined') {
  globalThis.TextEncoder = TextEncoder as typeof globalThis.TextEncoder
}
if (typeof globalThis.TextDecoder === 'undefined') {
  globalThis.TextDecoder = TextDecoder as typeof globalThis.TextDecoder
}
