# API errors

Every error the API returns has the same shape and speaks the language the client asks for. The decision is in [ADR 0004](../adr/0004-bilingual-interface-spanish-by-default.md) and [ADR 0007](../adr/0007-one-translated-problem-shape-for-every-api-error.md).

## Shape

Errors follow [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457) and are sent as `application/problem+json`:

```json
{
  "type": "urn:estiba:error:warehouse.warehouse_code_invalid",
  "title": "Unprocessable entity",
  "status": 422,
  "code": "warehouse.warehouse_code_invalid",
  "detail": "\"not valid!\" is not a valid warehouse code. Use 2 to 12 letters or digits, with single hyphens between groups.",
  "parameters": { "value": "not valid!" }
}
```

| Field | Meaning | Translated |
|---|---|---|
| `type` | `urn:estiba:error:` followed by the code | No |
| `title` | Short name of the HTTP status | Yes |
| `status` | HTTP status, repeated from the response | No |
| `code` | Stable identifier of the problem. **Switch on this**, not on `detail`. | No |
| `detail` | A sentence a person can read | Yes |
| `parameters` | The values the sentence mentions, with their real types | No |

## Language

The language comes from the `Accept-Language` header. Spanish (`es`) is the default and English (`en`) the only other option.

| Request header | Answer |
|---|---|
| none, or empty | Spanish |
| `es`, `es-ES` | Spanish |
| `en`, `en-GB` | English |
| `es;q=0.4, en;q=0.9` | English, because it has the higher quality |
| `fr-FR, fr;q=0.9` | Spanish, because the API does not speak French |

The response says which language it used in `Content-Language`, and carries `Vary: Accept-Language` so caches keep one copy per language.

```bash
curl -s -X POST http://localhost:8080/api/warehouses \
  -H 'Content-Type: application/json' -H 'X-Tenant-Id: 01929b3e-7c1a-7000-8000-000000000001' \
  -H 'Accept-Language: es' \
  -d '{"code": "not valid!", "name": "Madrid"}'
```

## HTTP status

| Status | When |
|---|---|
| 400 | The request cannot be read, or an identifier is not a UUID |
| 401 | The `X-Tenant-Id` header is missing |
| 404 | The resource or the route does not exist |
| 405 | The resource does not offer that method |
| 406 | The client asks for a format other than JSON |
| 409 | The change clashes with the current state: a code already used, a location already blocked |
| 415 | The request is not sent as JSON |
| 422 | The request is well formed but breaks a business rule |
| 500 | Something unexpected failed. The real cause is only in the server log. |

## Codes

The codes and their texts are the translation files, which are the single source of truth:

- [`errors+intl-icu.es.yaml`](../../backend/translations/errors+intl-icu.es.yaml)
- [`errors+intl-icu.en.yaml`](../../backend/translations/errors+intl-icu.en.yaml)

Codes are grouped by area (`shared`, `catalog`, `warehouse`) and never change once published.

## Adding a code

1. Raise a domain exception built with a named constructor (see `backend/src/Warehouse/Domain/Exception/`). Its code is `<area>.<rule>`, in lowercase.
2. Add its text to both translation files, with a `{placeholder}` for every parameter the text mentions. Avoid apostrophes: ICU treats them as special.
3. Run `make test-unit`. `ErrorCatalogTest` fails if a text is missing in a language, is left over for a code that no longer exists, is not valid ICU, or uses a parameter the error does not carry.
4. If the HTTP status is not the one `ApiErrorListener` derives from the code, adjust it there and cover it with a test.
