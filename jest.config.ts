import type { Config } from 'jest'
import nextJest from 'next/jest.js'

const createJestConfig = nextJest({ dir: './' })

const config: Config = {
  coverageProvider: 'v8',
  testEnvironment: 'jsdom',
  setupFilesAfterEnv: ['<rootDir>/jest.setup.ts'],
  moduleNameMapper: {
    // Specific mocks must come before the general @/ alias
    '^@/lib/prisma$': '<rootDir>/__mocks__/lib/prisma.ts',
    '^@/(.*)$': '<rootDir>/$1',
    '^next-auth$': '<rootDir>/__mocks__/next-auth.ts',
    '^next-auth/providers/credentials$': '<rootDir>/__mocks__/next-auth/providers/credentials.ts',
    '^next-auth/providers/discord$': '<rootDir>/__mocks__/next-auth/providers/discord.ts',
    '^@auth/prisma-adapter$': '<rootDir>/__mocks__/@auth/prisma-adapter.ts',
  },
}

export default createJestConfig(config)
