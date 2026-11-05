// Tests wallet linking end to end with a throwaway wallet.
// Usage:  node sign-wallet.mjs <collector_token>
import { Ed25519Keypair } from "@mysten/sui/keypairs/ed25519";

const [token, base = "http://127.0.0.1:8000/api"] = process.argv.slice(2);
const headers = { "Content-Type": "application/json", Accept: "application/json", Authorization: `Bearer ${token}` };

const ch = await (await fetch(`${base}/wallet/challenge`, { method: "POST", headers })).json();
const kp = Ed25519Keypair.generate();
const { signature } = await kp.signPersonalMessage(new TextEncoder().encode(ch.message));
const res = await fetch(`${base}/wallet/link`, {
  method: "POST",
  headers,
  body: JSON.stringify({ nonce: ch.nonce, address: kp.toSuiAddress(), signature }),
});
console.log(res.status, await res.json());
