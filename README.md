# Markd

Daily savings, finally accountable.

Markd gives market collectors and savers a tamper-proof ledger for daily contributions.
Collectors mark each day, savers get receipts and see their balance, and payouts go to the saver.

## Layout
- apps/api: Laravel + Postgres (system of record)
- apps/web: Next.js PWA (collector and saver apps)
- apps/ai: FastAPI assistant (Ask Markd)
- contracts: Sui Move (ledger anchor and collector bond)
- docs, brand, scripts
