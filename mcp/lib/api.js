/**
 * The HTTP side of the bridge. No business logic lives here: the rules about
 * statuses, resolutions and numbering belong to tsutsumbi, and duplicating any
 * of them in a client that can be out of date is how the two drift apart.
 */
export class TsutsumbiApi {
  #baseUrl;
  #token;
  #fetch;

  constructor({ baseUrl, token, fetch: fetchImpl = globalThis.fetch }) {
    if (typeof baseUrl !== 'string' || baseUrl.trim() === '') {
      throw new Error('TSUTSUMBI_URL is not set.');
    }

    if (typeof token !== 'string' || token.trim() === '') {
      throw new Error('TSUTSUMBI_TOKEN is not set.');
    }

    this.#baseUrl = baseUrl.trim().replace(/\/+$/, '');
    this.#token = token.trim();
    this.#fetch = fetchImpl;
  }

  get(path, query = {}) {
    return this.#request('GET', this.#withQuery(path, query));
  }

  post(path, body) {
    return this.#request('POST', path, body);
  }

  patch(path, body) {
    return this.#request('PATCH', path, body);
  }

  async postMultipart(path, formData) {
    return this.#send('POST', path, {
      // Content-Type is left to fetch, which has to add the multipart boundary.
      headers: { Authorization: `Bearer ${this.#token}`, Accept: 'application/json' },
      body: formData,
    });
  }

  async #request(method, path, body) {
    const headers = {
      Authorization: `Bearer ${this.#token}`,
      Accept: 'application/json',
    };

    if (body !== undefined) {
      headers['Content-Type'] = 'application/json';
    }

    return this.#send(method, path, {
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  }

  async #send(method, path, init) {
    const url = `${this.#baseUrl}${path}`;
    let response;

    try {
      response = await this.#fetch(url, { method, ...init });
    } catch (cause) {
      throw new ApiError(`tsutsumbi is unreachable at ${this.#baseUrl}: ${redact(cause?.message ?? '')}`);
    }

    const text = await response.text();
    const payload = text === '' ? null : safeJson(text);

    if (!response.ok) {
      throw new ApiError(describeFailure(response.status, payload), response.status, payload);
    }

    return payload;
  }

  #withQuery(path, query) {
    const params = new URLSearchParams();

    for (const [key, value] of Object.entries(query)) {
      if (value === undefined || value === null || value === '') {
        continue;
      }

      if (Array.isArray(value)) {
        for (const item of value) {
          params.append(`${key}[]`, String(item));
        }

        continue;
      }

      params.append(key, String(value));
    }

    const queryString = params.toString();

    return queryString === '' ? path : `${path}?${queryString}`;
  }
}

export class ApiError extends Error {
  constructor(message, status = null, problem = null) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.problem = problem;
  }
}

/**
 * Turns an RFC 7807 payload into one line an agent can act on. Validation
 * failures list their fields, because "the request was invalid" is not something
 * anybody can fix.
 */
function describeFailure(status, payload) {
  if (payload === null || typeof payload !== 'object') {
    return `tsutsumbi answered ${status}.`;
  }

  const parts = [payload.title ?? `HTTP ${status}`];

  if (typeof payload.detail === 'string' && payload.detail !== '') {
    parts.push(payload.detail);
  }

  if (payload.errors && typeof payload.errors === 'object') {
    for (const [field, messages] of Object.entries(payload.errors)) {
      parts.push(`${field}: ${[].concat(messages).join(' ')}`);
    }
  }

  return redact(parts.join(' — '));
}

function safeJson(text) {
  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

/**
 * Belt and braces on the one secret this process holds: a token must never
 * reach the model, a log line, or an error message on its way back out.
 */
export function redact(text) {
  return String(text).replace(/tsu_[0-9A-Za-z]{12}_[0-9A-Za-z]{43}/g, 'tsu_[redacted]');
}
