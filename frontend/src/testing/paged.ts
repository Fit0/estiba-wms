/** A list answer of the API: the items as a JSON array and how many there are in all in a header. */
export function paged(items: unknown[], total = items.length): Response {
  return new Response(JSON.stringify(items), {
    status: 200,
    headers: { 'Content-Type': 'application/json', 'X-Total-Count': String(total) },
  });
}
