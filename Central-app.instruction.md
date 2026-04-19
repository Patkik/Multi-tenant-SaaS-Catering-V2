I'm building CaterPro, a multi-tenant SaaS catering management platform built with Laravel 11/12, stancl/tenancy v3, MySQL, React, Tailwind CSS, and Inertia.js.
The system has two apps: a Central App (landlord/admin) and a Tenant App (per catering business). I want you to build a fully interactive multi-page mockup of the Central App only, rendered as a single HTML widget with a fixed-height sidebar + content area layout.

Layout structure:

Fixed left sidebar (≈192px wide) with: CaterPro logo + "Central Platform" subtitle at top, navigation items in the middle, and logged-in admin info at the bottom
Top bar with current page title on the left, and a version badge + avatar initials on the right
Scrollable content area on the right that changes based on the selected page


Include exactly 8 navigable pages. Clicking a nav item must switch the content area. Destroy and re-initialize any charts on each navigation to avoid canvas conflicts.
Page 1 — Dashboard

4 metric cards in a row: Total Tenants (128), Active (112), Monthly Revenue (₱89,432), Avg Plan (Basic)
A 2-column grid below: left = bar chart of tenant registrations for the last 6 months (Nov–Apr, values 14/18/22/17/25/19) using Chart.js; right = doughnut chart of plan distribution (Free 32, Basic 64, Premium 32)
Below that, a "Recent tenant activity" table with columns: Business Name, Subdomain, Plan badge, Status badge, Joined date — show 5 sample rows

Page 2 — Tenants

Search input, Plan filter dropdown, Status filter dropdown, and a "+ New Tenant" button that navigates to the wizard page
A full-width table with columns: Business Name, Subdomain, Plan, Status, Created, Actions — show 8 sample rows
Each row has View and either Suspend or Restore button depending on status
Footer showing "Showing 8 of 128 tenants"

Page 3 — New Tenant (Onboarding Wizard)

4-step horizontal stepper: Business Info → Subdomain → Plan → Confirm
Completed steps show a checkmark, current step is highlighted, future steps are muted
Step 1: Form with First name, Last name, Middle initial, Business name, Email, Password, Verify password
Step 2: Subdomain input joined with a ".caterpro.ph" suffix box, plus a green "Subdomain available" confirmation message
Step 3: 3 plan cards side by side — Free (₱0/mo), Basic (₱699/mo, "Most popular" badge with blue border), Premium (₱1,299/mo)
Step 4: Review summary table (Business, Owner, Subdomain, Plan, DB note), plus an amber info box explaining what provisioning does
Back and Continue/Provision buttons at the bottom; Back is disabled on step 1

Page 4 — Plans & Pricing

3 plan cards (Free, Basic, Premium) each showing: plan badge, Edit plan button, monthly price, tenant count, churn rate, user/event limits, and a checklist of included features
Below, a feature gate matrix table with rows for each feature and ✓ / ✕ per plan column

Page 5 — User Management

Search input and "+ Invite Admin" button
Table with columns: Name (with avatar initials circle), Email, Role badge, Status badge, Added date, Edit button
Show 4 sample admin users with roles: Super Admin, Support Admin, Billing Admin

Page 6 — Revenue Analytics (mark as NEW in sidebar)

4 metric cards: MRR (₱89,432), ARR (₱1,073,184), Avg churn rate (2.3%), ARPU (₱699)
2-column grid: left = 12-month MRR line chart trending from ₱54k to ₱89k; right = revenue-by-plan doughnut (Free ₱0, Basic ₱44,736, Premium ₱41,568, Misc ₱3,128) with a custom legend showing values and percentages
Full-width grouped bar chart: new vs. churned tenants for last 6 months

Page 7 — System Health (mark as NEW in sidebar)

4 metric cards: Tenant databases (128), Pending jobs (3), Failed jobs 24h (0), Avg API latency (48ms)
2-column grid: left = service health list with a colored dot, latency, uptime, and status badge per service (Landlord DB, Queue Worker, Redis Cache, S3 Storage, Mail Service — mark Mail as Degraded in amber, all others Healthy in teal); right = 5 resource usage bars (CPU 34%, Memory 61%, Disk landlord 28%, Disk tenant DBs 47%, Queue throughput 82%) plus a "Recent job events" mini-table below
Full-width 24-hour API response time line chart in purple

Page 8 — Audit Logs (mark as NEW in sidebar)

Search input, Action type dropdown, User dropdown
A vertical timeline with a connecting line on the left: each entry has a colored dot circle (success=teal, danger=coral, info=blue, warning=amber), action name in bold, target/detail in muted text, admin user below, and timestamp on the right
Show 10 log entries covering: tenant suspended, plan upgraded, tenant created, invoice generated, plan pricing edited, tenant restored, admin invited, bulk invoice run, feature gate toggled, tenant deleted
Footer showing "Showing 10 of 342 entries"


Design rules:

Use only CSS variables for all colors: --color-text-primary/secondary/tertiary, --color-background-primary/secondary/tertiary, --color-border-tertiary, --border-radius-md, --border-radius-lg
No hardcoded hex colors on text or backgrounds — only use hex inside Chart.js configs (canvas can't read CSS vars) and inside badge/dot inline styles from this palette: teal (#1D9E75 / #E1F5EE / #085041), coral (#D85A30 / #FAECE7 / #712B13), amber (#EF9F27 / #FAEEDA / #633806), blue (#378ADD / #E6F1FB / #0C447C), gray (#73726c / #F1EFE8 / #444441), purple (#7F77DD)
No gradients, no box shadows, no dark outer container background
All borders are 0.5px solid var(--color-border-tertiary) except one featured card accent which uses 2px solid in the plan wizard
Cards use background: var(--color-background-primary), metric cards use background: var(--color-background-secondary)
Load Chart.js from https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js
Every canvas must have role="img", aria-label, and fallback text
All charts must use responsive: true, maintainAspectRatio: false inside a position:relative wrapper div with an explicit pixel height
Disable Chart.js default legends and build custom HTML legends with colored squares instead
Use table-layout: fixed on all tables with explicit column widths
Font sizes: 21–22px for metric values, 15px for page title, 13–14px for section headings, 12px for body/table, 11–10px for badges and secondary labels
No DOCTYPE, no <html>, <head>, or <body> tags — output a raw HTML fragment only
Include a visually hidden <h2 class="sr-only"> at the top for screen reader accessibility