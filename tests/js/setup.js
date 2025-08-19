import { vi } from 'vitest'

// Mock axios
vi.mock('axios', () => ({
  default: {
    get: vi.fn(),
    post: vi.fn(),
    delete: vi.fn(),
    defaults: {
      headers: {
        common: {}
      }
    }
  }
}))

// Mock document.head for CSRF token
Object.defineProperty(document, 'head', {
  value: {
    querySelector: vi.fn(() => ({
      content: 'mock-csrf-token'
    }))
  }
})

// Mock window.location
Object.defineProperty(window, 'location', {
  value: {
    href: 'http://localhost',
    origin: 'http://localhost'
  },
  writable: true
})