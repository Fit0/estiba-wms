import { describe, expect, it } from 'vitest';
import { ApiError } from './ApiError';
import { describeError } from './describeError';

describe('describeError', () => {
  it('says the status and the code of an answer of the API', () => {
    expect(describeError(new ApiError(500, 'shared.internal_error', ''))).toBe(
      '500 shared.internal_error',
    );
    expect(describeError(new ApiError(409, 'warehouse.code_taken', 'Ese código ya existe.'))).toBe(
      '409 warehouse.code_taken: Ese código ya existe.',
    );
  });

  it('says the name and the message of anything else', () => {
    expect(describeError(new TypeError('Failed to fetch'))).toBe('TypeError: Failed to fetch');
    expect(describeError('boom')).toBe('boom');
  });
});
