import type { NextConfig } from 'next'

const nextConfig: NextConfig = {
  transpilePackages: ['nanoid', 'next-auth', '@auth/core', '@auth/prisma-adapter'],
}

export default nextConfig
