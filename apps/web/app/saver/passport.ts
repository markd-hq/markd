export type Passport = {
  empty: boolean;
  name: string | null;
  shared?: boolean;
  summary: { total_saved_naira: string; days_marked: number; saving_since: string | null; history_days: number; collectors: string[] };
  reliability: { score: number; status: string; label: string };
  readiness: { band: string; note: string };
  recent_records: { date: string; amount_naira: string; ref: string }[];
  disclaimer: string;
};
