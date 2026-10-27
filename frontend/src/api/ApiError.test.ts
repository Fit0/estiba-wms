import { describe, expect, it } from 'vitest';
import { ApiError, toApiError, unwrap, unwrapPage, UNEXPECTED_CODE } from './ApiError';

describe('toApiError', () => {
  it('reads the code, the detail and the parameters of a problem', () => {
    const error = toApiError(422, {
      code: 'warehouse.warehouse_code_invalid',
      detail: 'No vale.',
      parameters: { value: 'x' },
    });

    expect(error).toBeInstanceOf(ApiError);
    expect(error.status).toBe(422);
    expect(error.code).toBe('warehouse.warehouse_code_invalid');
    expect(error.message).toBe('No vale.');
    expect(error.parameters).toEqual({ value: 'x' });
  });

  it.each([undefined, null, 'Bad gateway', 42, {}])('is unexpected when the body is %j', (body) => {
    const error = toApiError(502, body);

    expect(error.code).toBe(UNEXPECTED_CODE);
    expect(error.status).toBe(502);
  });
});

describe('unwrap', () => {
  it('gives the data of a good answer', () => {
    expect(unwrap({ data: [1, 2], response: new Response(null, { status: 200 }) })).toEqual([1, 2]);
  });

  it('throws the problem of a bad one', () => {
    const failure = () =>
      unwrap({
        error: { code: 'inventory.stock_negative', detail: 'Negativo.' },
        response: new Response(null, { status: 409 }),
      });

    expect(failure).toThrow(ApiError);
    expect(failure).toThrow('Negativo.');
  });

  it('throws when a good answer has no body', () => {
    const failure = () => unwrap({ response: new Response(null, { status: 204 }) });

    expect(failure).toThrow(ApiError);
  });
});

describe('unwrapPage', () => {
  it('gives the items and the total of the header', () => {
    const response = new Response(null, { status: 200, headers: { 'X-Total-Count': '125' } });

    expect(unwrapPage({ data: ['a', 'b'], response })).toEqual({ items: ['a', 'b'], total: 125 });
  });

  it.each([undefined, 'many', '-1', '2.5'])(
    'counts the items shown when the header is %j',
    (value) => {
      const headers = value === undefined ? {} : { 'X-Total-Count': value };
      const response = new Response(null, { status: 200, headers });

      expect(unwrapPage({ data: ['a', 'b'], response }).total).toBe(2);
    },
  );

  it('throws the problem of a bad answer', () => {
    const response = new Response(null, { status: 403 });

    expect(() => unwrapPage({ error: { code: 'shared.forbidden' }, response })).toThrow(ApiError);
  });
});
