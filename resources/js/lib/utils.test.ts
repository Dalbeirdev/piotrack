import { describe, expect, it } from 'vitest';
import { cn } from './utils';

// The first test in the suite - it proves the Vitest harness resolves the @/*
// alias, runs TypeScript, and executes, as much as it tests cn() itself.
describe('cn', () => {
    it('joins class names', () => {
        expect(cn('a', 'b')).toBe('a b');
    });

    it('drops falsy values', () => {
        const hidden = false as boolean;
        expect(cn('a', hidden && 'b', undefined, 'c')).toBe('a c');
    });

    it('lets a later tailwind utility win over an earlier conflicting one', () => {
        // tailwind-merge behaviour: px-4 replaces px-2 rather than both surviving.
        expect(cn('px-2', 'px-4')).toBe('px-4');
    });
});
