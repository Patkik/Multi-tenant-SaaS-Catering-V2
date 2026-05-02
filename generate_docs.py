import os
from reportlab.lib.pagesizes import letter
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, ListFlowable, ListItem
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.colors import HexColor

def generate_pdf():
    doc = SimpleDocTemplate("CaterPro_System_Documentation.pdf", pagesize=letter)
    styles = getSampleStyleSheet()
    
    # Custom Styles
    title_style = ParagraphStyle(
        'MainTitle', 
        parent=styles['Title'], 
        fontSize=24, 
        spaceAfter=20,
        textColor=HexColor('#1a202c')
    )
    heading1 = ParagraphStyle(
        'Heading1', 
        parent=styles['Heading1'], 
        fontSize=18, 
        spaceBefore=15, 
        spaceAfter=10,
        textColor=HexColor('#2d3748')
    )
    heading2 = ParagraphStyle(
        'Heading2', 
        parent=styles['Heading2'], 
        fontSize=14, 
        spaceBefore=12, 
        spaceAfter=6,
        textColor=HexColor('#4a5568')
    )
    normal = ParagraphStyle(
        'Normal', 
        parent=styles['Normal'], 
        fontSize=11, 
        spaceAfter=8,
        leading=14
    )
    bullet = ParagraphStyle(
        'Bullet', 
        parent=styles['Normal'], 
        fontSize=11, 
        spaceAfter=4,
        leftIndent=20,
        leading=14
    )

    story = []

    # Title
    story.append(Paragraph("CaterPro SaaS System", title_style))
    story.append(Paragraph("Comprehensive Architectural & Technical Documentation", styles['Heading3']))
    story.append(Spacer(1, 20))
    story.append(Paragraph("This document provides a comprehensive blueprint, implementation strategy, and technical requirement specification for the CaterPro Multi-tenant SaaS Catering Management System.", normal))
    
    story.append(PageBreak())

    # 1. System Overview
    story.append(Paragraph("1. System Overview", heading1))
    story.append(Paragraph("CaterPro is a dual-application architecture built with Laravel (versions 11/12) and React. It operates through a Central Application (Landlord) for platform management and an isolated Tenant Application for business operations.", normal))
    story.append(Paragraph("Key Technological Stack:", heading2))
    story.append(Paragraph("• Backend: Laravel 11/12", bullet))
    story.append(Paragraph("• Frontend: React 19, Vite, TailwindCSS v4", bullet))
    story.append(Paragraph("• Tenancy Engine: stancl/tenancy v3", bullet))
    story.append(Paragraph("• Authentication: Laravel Sanctum", bullet))
    story.append(Paragraph("• Roles & Permissions: spatie/laravel-permission", bullet))
    story.append(Spacer(1, 10))

    # 2. Key Architectural Components
    story.append(Paragraph("2. Key Architectural Components", heading1))
    story.append(Paragraph("Tenancy Strategy", heading2))
    story.append(Paragraph("The system utilizes multi-database isolation where every tenant is provisioned with a dedicated MySQL/SQLite database. Tenant identification is performed via subdomain resolution. Storage, cache, and filesystem access are also strictly isolated per tenant using tenancy bootstrappers.", normal))
    
    story.append(Paragraph("Central App (Landlord)", heading2))
    story.append(Paragraph("Handles platform-level operations, including:", normal))
    story.append(Paragraph("• Tenant Provisioning (TenantProvisioningService)", bullet))
    story.append(Paragraph("• Subscription Tiering (Free, Starter, Business, Enterprise)", bullet))
    story.append(Paragraph("• Revenue Analytics & Global System Health", bullet))
    
    story.append(Paragraph("Tenant App (Business Context)", heading2))
    story.append(Paragraph("Scoped by tenancy middleware, the Tenant App includes core catering modules:", normal))
    story.append(Paragraph("• Event Management (lifecycle, Kanban, calendar view)", bullet))
    story.append(Paragraph("• Client Portal & CRM", bullet))
    story.append(Paragraph("• Staff Assignment & Scheduling", bullet))
    story.append(Paragraph("• Package Engineering (Menu Builder)", bullet))
    story.append(Paragraph("• Payments & Invoicing", bullet))
    
    story.append(PageBreak())

    # 3. State Management & Frontend Architecture
    story.append(Paragraph("3. State Management & Frontend Architecture", heading1))
    story.append(Paragraph("React Context (TenantProvider)", heading2))
    story.append(Paragraph("The core of the frontend state is managed by the TenantProvider. It handles runtime checks for tenant capabilities, active subscription features, and authenticated user roles. Recent refactoring shifted from unstable useQuery-based auth logic to a deterministic useState/useEffect pattern, eliminating redirect loops between login and workspace components.", normal))
    
    story.append(Paragraph("Dynamic UI & Branding", heading2))
    story.append(Paragraph("The UI utilizes CSS Custom Properties injected by the TenantProvider to allow for white-labeling per tenant. Tenants on higher-tier plans can customize primary colors and logos.", normal))

    # 4. Access Control & Subscription Tiers
    story.append(Paragraph("4. Access Control & Subscription Tiers", heading1))
    story.append(Paragraph("Roles and Permissions (RBAC)", heading2))
    story.append(Paragraph("Stored within the tenant database, the system defines four core roles:", normal))
    story.append(Paragraph("• Admin: Full access to all modules, including settings and billing.", bullet))
    story.append(Paragraph("• Manager: Access to events, clients, packages, staff, and analytics.", bullet))
    story.append(Paragraph("• Staff: Access to dashboard, events, and clients.", bullet))
    story.append(Paragraph("• Cashier: Access to dashboard, payments, and events.", bullet))

    story.append(Paragraph("Subscription Plans", heading2))
    story.append(Paragraph("• Free: Event Management (Limit 10 active events/month, 3 users)", bullet))
    story.append(Paragraph("• Starter: Adds Client Portal, Staff Assignment", bullet))
    story.append(Paragraph("• Business: Adds Advanced Analytics, Branding Controls", bullet))
    story.append(Paragraph("• Enterprise: Unlimited users/events, all features", bullet))

    story.append(PageBreak())

    # 5. Security & Operational Preferences
    story.append(Paragraph("5. Security & Operational Preferences", heading1))
    story.append(Paragraph("Feature Gating", heading2))
    story.append(Paragraph("Features are enforced via the EnsureTenantFeatureEnabled middleware on the backend, and the RequireTenantModule component on the frontend, ensuring strict compliance with subscription plans.", normal))
    
    story.append(Paragraph("Active Tenant Check", heading2))
    story.append(Paragraph("The EnsureTenantIsActive middleware blocks access to suspended tenant workspaces.", normal))

    story.append(Paragraph("Data Isolation", heading2))
    story.append(Paragraph("Strict separation ensures tenant queries are scoped to the current database instance, completely preventing cross-tenant data leakage.", normal))

    # 6. Workflows & Services
    story.append(Paragraph("6. Core Workflows & Services", heading1))
    story.append(Paragraph("Tenant Provisioning Flow", heading2))
    story.append(Paragraph("The TenantProvisioningService encapsulates the full lifecycle, including domain creation, database migration, and initial RBAC seeding (via TenantRbacSeeder).", normal))

    story.append(Paragraph("Event Quotas", heading2))
    story.append(Paragraph("The Event status system is tied to financial triggers managed by the EventQuotaService. Active event counts are strictly monitored based on plan limits.", normal))

    # Build PDF
    doc.build(story)

if __name__ == "__main__":
    generate_pdf()
    print("PDF generated successfully.")
