# Tenant Localhost Resolution Lessons (Durable)

- Fail closed on host-only fallback ambiguity: never auto-select the first tenant when multiple same-host tenants differ only by port.
- For local dev ergonomics, normalize legacy localhost :8080 preview URLs to the active app port.
- Preserve explicit non-8080 ports; do not rewrite intentional port selections.
- Include IPv6 loopback equivalents in localhost checks.
- Keep regression tests that explicitly cover ambiguity fail-closed behavior, localhost :8080 normalization, explicit non-8080 preservation, and IPv6 loopback handling.
