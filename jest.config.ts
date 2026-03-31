import type { Config } from 'jest'
import nextJest from 'next/jest.js'

const createJestConfig = nextJest({ dir: './' })

const config: Config = {
  coverageProvider: 'v8',
  testEnvironment: 'jsdom',
  setupFiles: ['<rootDir>/jest.polyfills.ts'],
  setupFilesAfterEnv: ['<rootDir>/jest.setup.ts'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/$1',
    '^next-auth$': '<rootDir>/__mocks__/next-auth.ts',
    '^next-auth/providers/credentials$': '<rootDir>/__mocks__/next-auth/providers/credentials.ts',
    '^next-auth/providers/discord$': '<rootDir>/__mocks__/next-auth/providers/discord.ts',
    '^@auth/prisma-adapter$': '<rootDir>/__mocks__/@auth/prisma-adapter.ts',
  },
}

export default createJestConfig(config)
