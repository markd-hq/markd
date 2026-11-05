export type Role = "saver" | "collector" | "admin";
export type User = {
  id: string;
  email: string;
  name: string | null;
  role: Role;
  language: string;
  referral_code: string;
  wallet_address: string | null;
  wallet_verified: boolean;
};

export class ApiError extends Error {
  constructor(public status: number, message: string) {
    super(message);
  }
}

const BASE = (process.env.NEXT_PUBLIC_API_URL ?? "").replace(/\/$/, "");
const TOKEN = "markd_token";
const USER = "markd_user";

export const session = {
  token: () => (typeof window === "undefined" ? null : localStorage.getItem(TOKEN)),
  user: (): User | null => {
    if (typeof window === "undefined") return null;
    try {
      return JSON.parse(localStorage.getItem(USER) ?? "null");
    } catch {
      return null;
    }
  },
  save: (token: string, user: User) => {
    localStorage.setItem(TOKEN, token);
    localStorage.setItem(USER, JSON.stringify(user));
  },
  clear: () => {
    localStorage.removeItem(TOKEN);
    localStorage.removeItem(USER);
  },
};

export const homeFor = (role: Role) => (role === "collector" ? "/collector/" : role === "admin" ? "/admin/" : "/saver/");

export async function call<T>(path: string, opts: { method?: string; body?: unknown; auth?: boolean } = {}): Promise<T> {
  const token = opts.auth === false ? null : session.token();
  let res: Response;
  try {
    res = await fetch(`${BASE}/api${path}`, {
      method: opts.method ?? "GET",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: opts.body ? JSON.stringify(opts.body) : undefined,
    });
  } catch {
    throw new ApiError(0, "Cannot connect to Markd. Check your connection and confirm the API is running with this website allowed in its CORS settings.");
  }
  const data = await res.json().catch(() => null);
  if (!res.ok) {
    const first = data?.errors ? (Object.values(data.errors)[0] as string[])?.[0] : null;
    throw new ApiError(res.status, first ?? data?.message ?? "Something went wrong. Please try again.");
  }
  return data as T;
}
