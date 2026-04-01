import { generateTotpSecret, verifyTotpToken } from '@/lib/totp'
import speakeasy from 'speakeasy'

describe('TOTP', () => {
  test('generateTotpSecret returns base32 secret and otpauth URL', () => {
    const result = generateTotpSecret('test@example.com')
    expect(result.secret).toBeTruthy()
    expect(result.otpauthUrl).toContain('otpauth://totp/')
    expect(result.otpauthUrl).toContain('CommunityOS')
  })

  test('verifyTotpToken returns true for valid token', () => {
    const { secret } = generateTotpSecret('test@example.com')
    const token = speakeasy.totp({ secret, encoding: 'base32' })
    expect(verifyTotpToken(secret, token)).toBe(true)
  })

  test('verifyTotpToken returns false for wrong token', () => {
    const { secret } = generateTotpSecret('test@example.com')
    expect(verifyTotpToken(secret, '000000')).toBe(false)
  })
})
