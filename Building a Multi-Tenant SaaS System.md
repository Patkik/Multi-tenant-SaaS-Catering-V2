# CaterPro Backend and Frontend Prompt Pack

Use this document as the implementation brief for building CaterPro with Laravel 11/12, stancl/tenancy v3, MySQL, React, Tailwind CSS, and Inertia.js or Axios.

## Backend System Prompt

**Role:** Senior Laravel Developer

**Task:** Build CaterPro as a multi-tenant SaaS with a landlord MySQL database for platform data and isolated MySQL tenant databases for each catering business.

### Requirements:
- Configure two database connections: `landlord` for central data and `tenant` for business data.
- Set up stancl/tenancy with `InitializeTenancyBySubdomain` and `PreventAccessFromCentralDomains`.
- Provision tenants through a service that validates unique subdomains, creates the tenant record, creates the domain record, assigns a subscription plan, and dispatches the tenant database migration job on `TenantCreated`.
- Seed new tenant databases with default roles, permissions, and starter data after migrations complete.
- Keep tenant queries tenancy-aware and avoid cross-tenant leakage.
- Implement central endpoints for registration, tenant onboarding, dashboard stats, and plan management.
- Implement middleware that checks the tenant plan before allowing plan-gated features such as Staff Assignment, Advanced Analytics, and Branding controls.
- Use `spatie/laravel-permission` inside each tenant database for RBAC with Admin, Manager, Staff, and Cashier roles.
- Enforce the Free plan limit of 10 active events per month.
- Create tenant migrations for `clients`, `catering_packages`, `events`, `payments`, `staff`, and `event_staff`.

## Frontend System Prompt

**Role:** Senior Frontend Engineer

**Task:** Build the CaterPro UI with React, Tailwind CSS, and Inertia.js or Axios-based API calls.

### Requirements:
- Create a `TenantProvider` that loads tenant profile data on startup: company name, logo, primary color, plan, and enabled features.
- Apply branding with CSS variables such as `--primary-color` and reference them in Tailwind utilities.
- Build a central dashboard with tenant stats, subscription breakdown, tenant management, and a step-by-step registration wizard.
- Build a tenant app layout with header, sidebar, and content area.
- Filter sidebar navigation by role and plan.
- Show Admin, Manager, Staff, and Cashier only the routes and modules they are allowed to use.
- Add a booking wizard for dates, guest count, package selection, and review.
- Render event status badges with clear color coding.
- Show locked UI states for unavailable plan features.
- Fetch and display tenant logos dynamically from tenant storage.

## Implementation Notes

- Use MySQL for both landlord and tenant databases.
- Keep central and tenant code paths separate.
- Prefer small services, middleware, and form requests over large controllers.
- Keep branding, routing, and feature gating data-driven so plan changes update the UI without code changes.

## Implementation Checklist

1. Set up landlord and tenant MySQL connections.
2. Install and configure stancl/tenancy with subdomain identification.
3. Build tenant provisioning, migration, and seeding flow.
4. Add RBAC and plan-based feature gates.
5. Create tenant migrations for core catering tables.
6. Build the tenant-aware React layout, provider, and sidebar guards.
7. Wire branding updates through tenant metadata and CSS variables.

## Appendix: Original Reference Material

# **Architectural Blueprint and Implementation Strategy for the CaterPro Multi-Tenant Catering Management System**

The paradigm of modern software distribution has shifted decisively toward Software-as-a-Service (SaaS), a model that necessitates the development of highly scalable, secure, and isolated digital environments. Within the catering and food service industry, where operational complexity ranges from menu engineering to intricate event logistics and staff orchestration, a robust multi-tenant management system is not merely a convenience but a structural requirement for business viability. CaterPro is conceived as a premier web-based multi-tenant Catering Management System designed to bridge the gap between shared infrastructure and independent business operations. By leveraging a dual-application architecture—comprising a central application and a tenant application—CaterPro ensures that each catering company, event provider, or food service entity operates in a strictly isolated context, utilizing dedicated databases and unique configurations while residing on a unified hosting instance.1

## **Theoretical Framework of Multi-Tenancy in SaaS Environments**

To understand the architecture of CaterPro, one must first explore the foundational concepts of multi-tenancy. Multi-tenancy represents the ability of a single software instance to serve multiple distinct users or groups, known as tenants.2 Unlike single-tenant deployments, where an application is installed separately for every client—leading to exponential increases in maintenance overhead and infrastructure costs—multi-tenancy allows for a singular, shared codebase.1 This centralized management ensures that security patches, feature updates, and performance optimizations are rolled out across the entire platform simultaneously, maintaining parity between all users.5

The architectural strategy adopted for CaterPro is multi-database tenancy. In this model, every tenant is provisioned with its own physical database upon registration. This provides the highest level of data isolation and security, as the risk of cross-tenant data leakage is physically mitigated at the database layer.2 For a catering business, which manages sensitive client information, proprietary recipes, and confidential financial reports, this isolation is paramount.7

| Tenancy Model | Data Isolation Level | Implementation Complexity | Cost Efficiency | Scaling Strategy |
| :---- | :---- | :---- | :---- | :---- |
| Single Database (Column Scoping) | Low; relies on tenant\_id filters 2 | Low; use of global scopes 9 | High; shared resources 2 | Horizontal scaling of a single DB 2 |
| Multiple Databases (Per Tenant) | High; physical separation of data 1 | High; requires automated provisioning 3 | Moderate; increased connection overhead 4 | Sharding and database clustering 2 |
| Schema-Based (Shared DB) | Moderate; logical separation in one DB 2 | Moderate; schema-aware migrations 11 | High; fewer connections than multi-DB 2 | Schema migration tools 11 |

CaterPro utilizes the stancl/tenancy framework for Laravel, which provides a sophisticated event-based system for managing these complex transitions. The framework operates in "automatic mode," where it identifies the tenant based on the request (typically via subdomain) and automatically switches the default database connection, cache prefixes, and filesystem roots to the tenant's specific context.1

## **The Dual-Application Architecture: Central and Tenant Logic**

The design of CaterPro is bifurcated into two primary components: the central application and the tenant application. This distinction is critical for the lifecycle management of the SaaS platform.14

### **The Central Application (Landlord Logic)**

The central application acts as the platform's infrastructure hub. It is executed in a context where no specific tenant is identified.14 Its primary responsibilities include marketing, tenant onboarding, subscription management, and global platform administration. When a prospective catering business visits the main CaterPro website, they interact with the central application to sign up for a plan.

The central application houses the following essential pages and functionalities:

* **Authentication Portal**: A comprehensive system for central administrators and new tenants. It includes login, registration, and "forget password" flows. The registration process is particularly detailed, requiring specific fields: username, lastname, MI (Middle Initial), firstname, password, and verify password.16  
* **Central Dashboard**: A global administrative interface providing high-level statistics, such as total active tenants, platform-wide revenue trends, and system health monitoring.14  
* **User Management**: The ability for global administrators to manage tenant-level administrative users.14  
* **Tenant Management**: A critical interface where administrators can configure tenant-specific permissions and feature access. This allows the platform to enable or disable specific pages or modules (e.g., the staff assignment module) based on the tenant's subscription tier.18  
* **Tenant Creation Pipeline**: The core engine that automates the provisioning of a new tenant domain and its dedicated database.11

### **The Tenant Application (Business Logic)**

The tenant application constitutes the actual service used by the catering companies and their staff. It is executed within the "tenant context," meaning all operations are scoped to that specific tenant's data.1 When a manager from "Acme Catering" logs into acme.caterpro.com, the system initializes the tenant context, connecting to Acme’s private database.13

The tenant application includes:

* **Tenant Authentication**: Dedicated login and registration pages for the catering company’s internal users (Managers, Staff, Cashiers). These pages mirror the central registration fields to maintain consistency across the platform.14  
* **Tenant Dashboard**: A role-based interface where the available sidebar links, header information, and page content adapt dynamically to the user's Role-Based Access Control (RBAC) settings.23  
* **Core Catering Modules**: The functional heart of the system, including client profiling, event booking, package selection, and payment tracking.8

## **Strategic Implementation of Tenant Identification**

For the CaterPro application to be tenant-aware, the system must identify which tenant is making a request. This identification occurs at the middleware layer of the web application.1 CaterPro primarily utilizes subdomain identification, which offers a professional and scalable URL structure for catering businesses (e.g., brandname.caterpro.com).3

| Identification Method | Mechanism | Use Case | Implementation Class |
| :---- | :---- | :---- | :---- |
| Subdomain | Extracts the first segment of the hostname 16 | Default for SaaS clients 3 | InitializeTenancyBySubdomain |
| Custom Domain | Matches the full hostname (e.g., events.com) 20 | Premium clients with own branding 3 | InitializeTenancyByDomain |
| Path-Based | Uses a URL prefix (e.g., caterpro.com/tenant1) 3 | Simple development or testing | InitializeTenancyByPath |
| Request Header | Looks for a X-Tenant-ID in the header 2 | Mobile applications or API-first logic | InitializeTenancyByRequestData |

Upon successful identification, the system fires the TenancyInitialized event. This triggers a sequence of "Bootstrappers" that reconfigure the Laravel application state. Specifically, the database connection is swapped, the cache is prefixed with the tenant ID to prevent cross-contamination, and the filesystem root is adjusted so that uploaded logos or event floor plans are stored in a private directory.1

## **Data Modeling for Catering Operations**

A catering management system requires a specialized schema capable of handling the temporal and resource-intensive nature of events. The database design must account for the relationships between clients, packages, events, and personnel.7

### **Core Entity Relationships**

The primary entities within the tenant application database include:

* **Clients**: Detailed records of individuals or organizations booking catering services, including contact information and profiling data.7  
* **Catering Packages**: Predefined sets of menu items and services offered at specific prices. Packages can be priced as a flat fee or on a per-person basis.26  
* **Events**: The central transactional record, linking a client to a specific package, date, and location. Events progress through a lifecycle of statuses: Pending, Confirmed, Completed, and Cancelled.8  
* **Payments**: A ledger tracking financial transactions for an event, differentiating between initial downpayments and final balance payments.8  
* **Staff and Assignments**: A many-to-many relationship where staff members are assigned to specific events in roles such as Server, Manager, or Chef, with recorded start and end times.7

| Table Name | Description | Key Relationships |
| :---- | :---- | :---- |
| clients | Stores customer demographics and history 7 | id (PK) |
| catering\_packages | Defines menu options and pricing tiers 26 | id (PK), price\_code |
| events | Records event logistics and status 7 | client\_id (FK), package\_id (FK) |
| payments | Tracks deposits and final settlement 8 | event\_id (FK) |
| staff | Directory of available personnel 7 | id (PK) |
| event\_staff | Pivot table for event-specific shift assignments 7 | event\_id (FK), staff\_id (FK) |

## **User Roles and Access Control (RBAC) Architecture**

CaterPro employs a sophisticated Role-Based Access Control (RBAC) system to ensure that users only interact with the data and functionality appropriate for their position. In a catering environment, this prevents unauthorized financial access while ensuring staff members can access their schedules efficiently.23

### **The Hierarchy of User Roles**

The system recognizes four primary roles within the tenant application:

* **Admin**: The business owner or system administrator within the catering company. They possess full system access, including the ability to manage all other users, configure global package pricing, view comprehensive financial reports, and customize the company’s branding settings (name, logo, theme color).25  
* **Manager**: Focused on operational execution. Managers have the authority to approve event bookings, monitor the status of ongoing events, and view operational reports, but they are restricted from altering the fundamental pricing structures or system configurations.23  
* **Staff**: Field employees who require access to event details. Their access is restricted to viewing events specifically assigned to them and updating the status of tasks within those events.25  
* **Cashier**: Dedicated financial personnel. Their scope is limited to recording payments, monitoring outstanding balances, and generating official receipts for clients.25

This RBAC system is typically implemented using the spatie/laravel-permission package, which integrates seamlessly with the tenant-specific database.24 Permissions are stored in the tenant's private database, allowing "Admin" roles to have different granularities of power from one catering company to another if necessary.

## **Subscription Monetization and Feature Gating**

CaterPro follows a tiered subscription model that balances accessibility for small businesses with advanced features for enterprise-level catering firms. The platform uses a "Feature Flag" mechanism to enforce these tiers at the application level.2

### **The Tiered Pricing Model**

| Plan Tier | Monthly Price | User Limit | Event Quota | Key Features Enabled |
| :---- | :---- | :---- | :---- | :---- |
| **Free** | ₱0 | 5 Users | 10 Events/Month | Basic booking, Name customization 34 |
| **Basic** | ₱699 | 8 Users | Unlimited | Payment tracking, Logo upload, Basic reports 35 |
| **Premium** | ₱1,299 | Unlimited | Unlimited | Staff assignments, Advanced analytics, Activity logs 18 |

### **Mechanism of Feature Gating**

Feature gating is implemented through a combination of middleware and Blade/React directives. When the central application manages a tenant, it records the subscription\_plan\_id in the tenant's metadata. During the tenant application lifecycle, the system checks this metadata to determine access.18 For example, if a tenant on the "Free" plan attempts to access the StaffAssignmentController, a middleware check will intercept the request and return a 403 status code, prompting the user to upgrade.18

In the frontend, the UI adapts dynamically:

* **Navigation**: The "Staff Assignment" link in the sidebar is hidden or shown with a "locked" icon based on the feature flag.18  
* **Data Scoping**: For "Free" plans, the event creation logic includes a check against the current month's event count to ensure the quota of 10 is not exceeded.34  
* **Customization**: The "Custom Logo Upload" interface is only rendered for "Basic" and "Premium" subscribers.27

## **Customization and Dynamic Branding Architecture**

A core value proposition of CaterPro is the ability for tenants to maintain their brand identity while using a shared platform. This "White Labeling" capability requires a dynamic approach to frontend styling.27

### **Dynamic Theme Engine**

CaterPro utilizes CSS Custom Properties (Variables) to handle dynamic branding. Instead of hardcoding hex values into the CSS, the application uses variables such as \--brand-primary and \--brand-secondary.

When the tenant application loads:

1. The system retrieves the tenant’s branding configuration from the database (Company Name, Logo URL, Theme Color).39  
2. The React ThemeProvider injects these values into the :root element of the DOM:  
   CSS  
   :root {  
     \--brand-primary: \#hex\_from\_db;  
     \--brand-logo: url('path\_from\_db');  
   }

3. Tailwind CSS classes are configured to reference these variables (e.g., bg-\[var(--brand-primary)\]), ensuring that the entire interface updates instantaneously when the Admin changes the settings.27

### **Logo and Name Management**

The tenant's company name and logo are treated as dynamic assets. The central application provides the storage infrastructure, but the tenant application manages the specific display. Logos are stored in a tenant-prefixed S3 bucket or local directory, ensuring that a request for logo.png from tenantA.caterpro.com resolves to a different file than the same request from tenantB.caterpro.com.1

## **Backend Implementation Specification (The Backend Prompt)**

Developing the CaterPro backend requires a disciplined approach to Laravel's service container and event system. The following blueprint provides the technical requirements for a senior-level implementation.

### **System Configuration and Initialization**

The developer must install stancl/tenancy and configure the TenancyServiceProvider. The system must be set up to use InitializeTenancyBySubdomain and PreventAccessFromCentralDomains middleware on all tenant-specific routes.1

### **Tenant Provisioning Service**

The core of the central app is the TenantProvisioningService. This service must:

1. Validate registration data (username, names, passwords).  
2. Create the Tenant record in the central tenants table.  
3. Create a Domain record mapped to the chosen subdomain.  
4. Trigger the automated migration of the tenant database using the Jobs\\MigrateDatabase pipeline.16  
5. Seed the new database with default roles and permissions.24

### **Multi-Database Query Logic**

While automatic mode handles connection switching, the developer must ensure that any cross-tenant communication (if required) is handled via a global database connection. For standard catering logic (Events, Packages, Payments), the code should remain "tenancy-agnostic," writing standard Eloquent queries like Event::all() which the package will automatically scope to the correct database.1

### **RBAC and Feature Gating Middleware**

A custom middleware, CheckSubscriptionFeature, must be developed. This middleware will:

1. Access the current tenant's subscription tier.  
2. Check the requested route against an array of features permitted for that tier.  
3. Allow or deny access based on the mapping (e.g., denying staff-management to Free plan users).18

## **Frontend Implementation Specification (The Frontend Prompt)**

The CaterPro frontend is a high-performance React application designed for modularity and a role-specific user experience.

### **State Management and Tenant Context**

The application must use a TenantProvider context at the root level. Upon initialization, this context performs an API call to the central application to fetch the tenant's profile (name, logo, theme, features). This profile is then used to populate the sidebar and set the CSS variables.39

### **Dynamic Routing and RBAC**

The routing system must be "guarded" by role-based checks. Using a ProtectedRoute component, the system should evaluate the user's role before rendering a page:

* **Admin**: Access to all routes including /settings/branding and /users/manage.25  
* **Staff**: Only access to /events/assigned and /profile.25  
* **Cashier**: Access to /payments and /invoicing.25

### **Dashboard and UI Components**

The UI should be built using a "Header \+ Sidebar \+ Content" layout. The Sidebar component must filter its navigation links based on both the subscription\_plan and the user\_role.18 For example, the "Advanced Analytics" link should only appear if the tenant is on the Premium plan and the user has at least a Manager role.

The "Customizable Branding" module in the Admin settings must provide a color picker for the theme color and a file upload for the logo. Upon saving, the backend updates the tenant metadata, which triggers a re-fetch and immediate UI update via the ThemeProvider.27

## **Advanced Event Lifecycle and Payment Logic**

CaterPro is not just a database; it is a workflow engine. The transition of an event status from Pending to Confirmed must be tied to financial triggers.

### **The Downpayment Workflow**

In the catering industry, an event is rarely "Confirmed" without a deposit. The system must enforce a policy where:

1. An event is created as Pending.  
2. The Cashier records a Payment of type Downpayment.  
3. The system automatically prompts the Manager to update the status to Confirmed once the downpayment reaches a configurable percentage (e.g., 50%) of the total package price.8

### **Staff Allocation Intelligence**

The optional Staff Assignment module (Premium Plan) adds a layer of complexity. When a Manager assigns staff to an event, the system must check for "Scheduling Conflicts." This involves querying the event\_staff table across all of the tenant's events to ensure a staff member is not double-booked for the same date and time.7

## **Data Security and Resilience in a Multi-Tenant Environment**

Operating a multi-tenant platform requires a high degree of defensive programming and infrastructure planning.

### **Prevention of Data Leakage**

While separate databases provide physical isolation, "Rogue" queries must be prevented. The stancl/tenancy package protects against this by ensuring that when the application is in tenant context, the "central" database is unreachable except through explicit, intentional connections.9

### **Automated Backup and Recovery**

Each tenant database must be part of an automated backup rotation. Since databases are isolated, CaterPro can offer a "Selective Restore" feature where an individual tenant can roll back their data to a previous point in time without impacting any other catering business on the platform.2

## **Conclusion and Strategic Outlook**

The development of the CaterPro Catering Management System represents a significant architectural undertaking that combines the complexities of catering logistics with the rigorous requirements of multi-tenant SaaS engineering. By adopting a dual-application architecture and a multi-database isolation strategy, CaterPro provides a secure, professional, and highly customizable environment for catering businesses of all sizes.1 The integration of tiered pricing, feature gating, and dynamic branding allows the platform to serve as a versatile business tool that scales alongside its users.2 As the food service industry continues to digitize, platforms like CaterPro will be essential for operational efficiency, financial transparency, and brand differentiation.5 Through careful implementation of the backend and frontend specifications detailed in this report, developers can build a system that is not only functional but resilient, ensuring the long-term success of the CaterPro SaaS ecosystem.1

#### **Works cited**

1. Tenancy for Laravel, accessed April 18, 2026, [https://tenancyforlaravel.com/](https://tenancyforlaravel.com/)  
2. Multi-Tenant SaaS Architecture: Complete Guide, Models, Design Patterns, and Scaling Strategy | Codeboxr, accessed April 18, 2026, [https://codeboxr.com/multi-tenant-saas-architecture-complete-guide-models-design-patterns-and-scaling-strategy/](https://codeboxr.com/multi-tenant-saas-architecture-complete-guide-models-design-patterns-and-scaling-strategy/)  
3. Laravel Multi-Tenancy: Managing Multiple Clients with Precision | by Cubet | Medium, accessed April 18, 2026, [https://medium.com/@Cubet/laravel-multi-tenancy-managing-multiple-clients-with-precision-5b76c4545ce6](https://medium.com/@Cubet/laravel-multi-tenancy-managing-multiple-clients-with-precision-5b76c4545ce6)  
4. How to Implement Multi-tenancy in Laravel \- OneUptime, accessed April 18, 2026, [https://oneuptime.com/blog/post/2026-02-02-laravel-multi-tenancy/view](https://oneuptime.com/blog/post/2026-02-02-laravel-multi-tenancy/view)  
5. Laravel SaaS Development Guide (2026): Architecture, Cost & Multi-Tenancy Explained, accessed April 18, 2026, [https://blog.binary-fusion.com/laravel-saas-development-guide/](https://blog.binary-fusion.com/laravel-saas-development-guide/)  
6. Compared to other packages | Tenancy for Laravel, accessed April 18, 2026, [https://tenancyforlaravel.com/docs/v3/package-comparison/](https://tenancyforlaravel.com/docs/v3/package-comparison/)  
7. Catering Business ER Diagram Guide | PDF \- Scribd, accessed April 18, 2026, [https://www.scribd.com/document/531362866/20-ERD-Problem-Catering-business](https://www.scribd.com/document/531362866/20-ERD-Problem-Catering-business)  
8. Catering Management System \- Synergism.io, accessed April 18, 2026, [https://synergism.io/catering-management-system/](https://synergism.io/catering-management-system/)  
9. Building Multi-Tenant SaaS Applications with Laravel 12 | NeedLaravelSite, accessed April 18, 2026, [https://needlaravelsite.com/blog/building-multi-tenant-saas-applications-with-laravel-12](https://needlaravelsite.com/blog/building-multi-tenant-saas-applications-with-laravel-12)  
10. Effective Multi Tenancy in Laravel \- Zignuts Technolab, accessed April 18, 2026, [https://www.zignuts.com/question-and-answer/how-to-implement-multi-tenancy-in-laravel-applications-effectively](https://www.zignuts.com/question-and-answer/how-to-implement-multi-tenancy-in-laravel-applications-effectively)  
11. Advanced Multi-Tenancy Solutions. Introduction | by Hareesh Ponnam | Medium, accessed April 18, 2026, [https://medium.com/@harshaponnam09/professional-presence-advanced-multi-tenancy-solutions-7089ec48f7d0](https://medium.com/@harshaponnam09/professional-presence-advanced-multi-tenancy-solutions-7089ec48f7d0)  
12. Multitenancy and Laravel's stancl/tenancy: A Love Letter to Organized Chaos \- Medium, accessed April 18, 2026, [https://medium.com/@DaveLumAI/multitenancy-and-laravels-stancl-tenancy-a-love-letter-to-organized-chaos-6a514d7f1c2c](https://medium.com/@DaveLumAI/multitenancy-and-laravels-stancl-tenancy-a-love-letter-to-organized-chaos-6a514d7f1c2c)  
13. Automatic mode \- Tenancy for Laravel, accessed April 18, 2026, [https://tenancyforlaravel.com/docs/v3/automatic-mode/](https://tenancyforlaravel.com/docs/v3/automatic-mode/)  
14. How to make a Laravel app multi-tenant in minutes, accessed April 18, 2026, [https://laravel-news.com/multi-tenant](https://laravel-news.com/multi-tenant)  
15. Central App \- Tenancy for Laravel, accessed April 18, 2026, [https://tenancyforlaravel.com/docs/v2/central-app/](https://tenancyforlaravel.com/docs/v2/central-app/)  
16. Creating a Multi-Tenant Application with Laravel and Neon \- DEV Community, accessed April 18, 2026, [https://dev.to/neon-postgres/creating-a-multi-tenant-application-with-laravel-and-neon-2kp2](https://dev.to/neon-postgres/creating-a-multi-tenant-application-with-laravel-and-neon-2kp2)  
17. Creating a Multi-Tenant Application with Laravel and Neon \- Neon Guides, accessed April 18, 2026, [https://neon.com/guides/laravel-multi-tenant-app](https://neon.com/guides/laravel-multi-tenant-app)  
18. I Built a Feature Flag Package for Laravel — Here's How It Compares to Pennant \- Medium, accessed April 18, 2026, [https://medium.com/@sohaibbilalnada/i-built-a-feature-flag-package-for-laravel-heres-how-it-compares-to-pennant-ebc1e03e3d72](https://medium.com/@sohaibbilalnada/i-built-a-feature-flag-package-for-laravel-heres-how-it-compares-to-pennant-ebc1e03e3d72)  
19. Building A Scalable Multi-tenant Saas Application With Laravel \- Prateeksha Web Design, accessed April 18, 2026, [https://prateeksha.com/blog/building-a-scalable-multi-tenant-saas-application-with-laravel](https://prateeksha.com/blog/building-a-scalable-multi-tenant-saas-application-with-laravel)  
20. Quickstart Tutorial \- Tenancy for Laravel, accessed April 18, 2026, [https://tenancyforlaravel.com/docs/v3/quickstart/](https://tenancyforlaravel.com/docs/v3/quickstart/)  
21. How to use laravel multi tenant (stancl/tenancy) with single DB ? \- InfyOm Technologies, accessed April 18, 2026, [https://infyom.com/blog/how-to-use-multi-tenant-with-single-db/](https://infyom.com/blog/how-to-use-multi-tenant-with-single-db/)  
22. Tenant identification | Tenancy for Laravel, accessed April 18, 2026, [https://tenancyforlaravel.com/docs/v3/tenant-identification/](https://tenancyforlaravel.com/docs/v3/tenant-identification/)  
23. How to implement role-based access control in a multi-tenant SaaS application?, accessed April 18, 2026, [https://stackoverflow.com/questions/79896042/how-to-implement-role-based-access-control-in-a-multi-tenant-saas-application](https://stackoverflow.com/questions/79896042/how-to-implement-role-based-access-control-in-a-multi-tenant-saas-application)  
24. Implementing Role-Based Access Control (RBAC) in Laravel 12 | NeedLaravelSite, accessed April 18, 2026, [https://needlaravelsite.com/blog/implementing-role-based-access-control-rbac-in-laravel-12](https://needlaravelsite.com/blog/implementing-role-based-access-control-rbac-in-laravel-12)  
25. Users, Roles and Permissions \- Laravel Daily, accessed April 18, 2026, [https://laraveldaily.com/lesson/laravel-vue-inertia-food-delivery/users-roles-permissions](https://laraveldaily.com/lesson/laravel-vue-inertia-food-delivery/users-roles-permissions)  
26. Catering Management Software \- EventPro, accessed April 18, 2026, [https://www.eventpro.net/catering-management-software.html](https://www.eventpro.net/catering-management-software.html)  
27. Beyond Hardcoding: 3 Ways to Handle Dynamic Colors in React & Tailwind CSS \- Medium, accessed April 18, 2026, [https://medium.com/@hridoycodev/beyond-hardcoding-3-ways-to-handle-dynamic-colors-in-react-tailwind-css-d397fb1ef80a](https://medium.com/@hridoycodev/beyond-hardcoding-3-ways-to-handle-dynamic-colors-in-react-tailwind-css-d397fb1ef80a)  
28. Automatic Tenancy For Your Laravel App \- Laravel News, accessed April 18, 2026, [https://laravel-news.com/stancl-tenancy-package](https://laravel-news.com/stancl-tenancy-package)  
29. Online Food Delivery \- Entity-relationship diagram example \- Gleek.io, accessed April 18, 2026, [https://www.gleek.io/templates/food-delivery-erd](https://www.gleek.io/templates/food-delivery-erd)  
30. Configuring Catering Package Pricing \- Oracle Help Center, accessed April 18, 2026, [https://docs.oracle.com/en/industries/hospitality/opera-cloud/25.1/ocsuh/t\_osem\_configuring\_catering\_package\_pricing.htm](https://docs.oracle.com/en/industries/hospitality/opera-cloud/25.1/ocsuh/t_osem_configuring_catering_package_pricing.htm)  
31. ER Diagram for Restaurant Management System \- YouTube, accessed April 18, 2026, [https://www.youtube.com/watch?v=Cq\_ZidVZOHE](https://www.youtube.com/watch?v=Cq_ZidVZOHE)  
32. User permissions and roles in Laravel \- Honeybadger Developer Blog, accessed April 18, 2026, [https://www.honeybadger.io/blog/laravel-permissions-roles/](https://www.honeybadger.io/blog/laravel-permissions-roles/)  
33. Multi-Role System in Laravel \- DEV Community, accessed April 18, 2026, [https://dev.to/dcodemania/multi-role-system-in-laravel-114c](https://dev.to/dcodemania/multi-role-system-in-laravel-114c)  
34. How to Implement Rate Limiting in Laravel \- OneUptime, accessed April 18, 2026, [https://oneuptime.com/blog/post/2026-02-03-laravel-rate-limiting/view](https://oneuptime.com/blog/post/2026-02-03-laravel-rate-limiting/view)  
35. Laravel Cashier: Managing Subscriptions And One-Time Charges Easily \- DEV Community, accessed April 18, 2026, [https://dev.to/aleson-franca/laravel-cashier-managing-subscriptions-and-one-time-charges-easily-5ekk](https://dev.to/aleson-franca/laravel-cashier-managing-subscriptions-and-one-time-charges-easily-5ekk)  
36. A guide to feature flags in Laravel using Laravel Pennant \- Honeybadger Developer Blog, accessed April 18, 2026, [https://www.honeybadger.io/blog/a-guide-to-feature-flags-in-laravel/](https://www.honeybadger.io/blog/a-guide-to-feature-flags-in-laravel/)  
37. Overview \- Laravel Nightwatch, accessed April 18, 2026, [https://nightwatch.laravel.com/docs/events](https://nightwatch.laravel.com/docs/events)  
38. Database: Query Builder | Laravel 13.x \- The clean stack for Artisans and agents, accessed April 18, 2026, [https://laravel.com/docs/13.x/queries](https://laravel.com/docs/13.x/queries)  
39. Dynamic Theming in React Using Context API: Multi-Brand \- DEV Community, accessed April 18, 2026, [https://dev.to/yorgie7/dynamic-theming-in-react-using-context-api-multi-brand-56l1](https://dev.to/yorgie7/dynamic-theming-in-react-using-context-api-multi-brand-56l1)  
40. Build a Flawless, Multi-Theme System using New Tailwind CSS v4 & React \- Medium, accessed April 18, 2026, [https://medium.com/render-beyond/build-a-flawless-multi-theme-ui-using-new-tailwind-css-v4-react-dca2b3c95510](https://medium.com/render-beyond/build-a-flawless-multi-theme-ui-using-new-tailwind-css-v4-react-dca2b3c95510)  
41. Creating Dynamic TailwindCSS Themes for a React Library | DoltHub Blog, accessed April 18, 2026, [https://www.dolthub.com/blog/2024-03-20-dynamic-tailwind-themes/](https://www.dolthub.com/blog/2024-03-20-dynamic-tailwind-themes/)  
42. Tenant Storage \- Tenancy for Laravel, accessed April 18, 2026, [https://tenancyforlaravel.com/docs/v2/tenant-storage/](https://tenancyforlaravel.com/docs/v2/tenant-storage/)  
43. Tenants | Tenancy for Laravel, accessed April 18, 2026, [https://tenancy-v4.pages.dev/tenants/](https://tenancy-v4.pages.dev/tenants/)  
44. Multi tenancy connect with tenant DB without Initialization Or Dynamically \- DEV Community, accessed April 18, 2026, [https://dev.to/arkdevsolutions/multi-tenancy-connect-with-tenant-db-without-initialization-or-dynamically-2hco](https://dev.to/arkdevsolutions/multi-tenancy-connect-with-tenant-db-without-initialization-or-dynamically-2hco)  
45. 5 Ways Catering Management Software Can Streamline Your Small Catering Business, accessed April 18, 2026, [https://totalpartyplanner.com/five-ways-catering-management-software-can-streamline-your-small-catering-business/](https://totalpartyplanner.com/five-ways-catering-management-software-can-streamline-your-small-catering-business/)