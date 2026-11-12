const API_BASE = import.meta.env.VITE_API_BASE_URL ?? '';

export class ApiError extends Error {
  constructor(message, { status = 0, code = 'NETWORK_ERROR', details = null } = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.code = code;
    this.details = details;
  }
}

function cookie(name) {
  const part = document.cookie.split('; ').find((item) => item.startsWith(`${name}=`));
  return part ? decodeURIComponent(part.slice(name.length + 1)) : '';
}

export async function request(path, { method = 'GET', body, headers = {}, ...options } = {}) {
  const upperMethod = method.toUpperCase();
  const mutating = !['GET', 'HEAD', 'OPTIONS'].includes(upperMethod);
  const serializedBody = body === undefined ? undefined : JSON.stringify(body);
  const readXsrf = async () => {
    const csrfResponse = await fetch(`${API_BASE}/sanctum/csrf-cookie`, { credentials: 'include' });
    if (!csrfResponse.ok) throw new ApiError('Không lấy được mã bảo vệ phiên. Hãy thử lại.', { status: csrfResponse.status, code: 'CSRF_UNAVAILABLE' });
  };
  if (mutating && !cookie('XSRF-TOKEN')) await readXsrf();

  const send = () => fetch(`${API_BASE}${path}`, {
    ...options,
    method: upperMethod,
    credentials: 'include',
    headers: {
      Accept: 'application/json',
      ...(serializedBody === undefined ? {} : { 'Content-Type': 'application/json' }),
      ...(mutating && cookie('XSRF-TOKEN') ? { 'X-XSRF-TOKEN': cookie('XSRF-TOKEN') } : {}),
      ...headers,
    },
    body: serializedBody,
  });

  let response = await send();
  if (mutating && response.status === 419) {
    await readXsrf();
    response = await send();
  }

  if (response.status === 204) return null;
  const payload = await response.json().catch(() => null);
  if (!response.ok) {
    const error = payload?.error ?? {};
    const message = error.message ?? payload?.message ?? Object.values(payload?.errors ?? {})[0]?.[0] ?? 'Đã xảy ra lỗi khi kết nối máy chủ.';
    throw new ApiError(message, {
      status: response.status,
      code: error.code ?? (response.status === 419 ? 'CSRF_EXPIRED' : response.status === 422 ? 'VALIDATION_ERROR' : response.status === 403 ? 'FORBIDDEN' : response.status === 401 ? 'UNAUTHENTICATED' : 'REQUEST_FAILED'),
      details: error.details ?? payload?.errors ?? null,
    });
  }
  return payload;
}

export function unwrapData(payload) {
  return payload?.data ?? payload;
}
