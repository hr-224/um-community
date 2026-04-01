import speakeasy from 'speakeasy'

export function generateTotpSecret(email: string) {
  const secret = speakeasy.generateSecret({
    name: `CommunityOS (${email})`,
    issuer: 'CommunityOS',
    length: 20,
  })
  return {
    secret: secret.base32,
    otpauthUrl: secret.otpauth_url ?? '',
  }
}

export function verifyTotpToken(secret: string, token: string): boolean {
  return speakeasy.totp.verify({
    secret,
    encoding: 'base32',
    token,
    window: 1,
  })
}
