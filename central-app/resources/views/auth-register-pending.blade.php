<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Registration Pending' }} - {{ $tenantName }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --coral: #d14b38;
            --ink: #15191f;
            --mustard: #d89a2a;
            --paper: #f6f2e8;
            --slate: #2f4254;
            --teal: #2a8573;
        }
    </style>
</head>
<body class="min-h-screen bg-[#f6f2e8] flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-lg border border-[#15191f]/10 p-8 text-center">
            <!-- Icon -->
            <div class="mx-auto w-20 h-20 bg-[#d89a2a]/15 rounded-full flex items-center justify-center mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-[#d89a2a]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>

            <!-- Title -->
            <h1 class="text-2xl font-bold text-[#15191f] mb-2">Registration Pending Approval</h1>
            
            <!-- Subtitle -->
            <p class="text-[#2f4254] mb-6">
                Your admin account request for <strong>{{ $tenantName }}</strong> has been submitted and is awaiting approval.
            </p>

            <!-- Details Card -->
            <div class="bg-[#f6f2e8] rounded-xl p-4 mb-6 text-left space-y-2">
                <div class="flex justify-between items-center">
                    <span class="text-sm text-[#2f4254]">Name</span>
                    <span class="font-medium text-[#15191f]">{{ $name }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-[#2f4254]">Email</span>
                    <span class="font-medium text-[#15191f]">{{ $email }}</span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-sm text-[#2f4254]">Requested Role</span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#d89a2a]/15 text-[#d89a2a] capitalize">
                        {{ $role }}
                    </span>
                </div>
            </div>

            <!-- Notice -->
            <div class="bg-[#2a8573]/10 rounded-lg p-4 mb-6">
                <div class="flex items-start gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#2a8573] flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <p class="text-sm text-[#2a8573] text-left">
                        A tenant administrator will review your request. You'll receive an email once your account is approved.
                    </p>
                </div>
            </div>

            <!-- Actions -->
            <div class="space-y-3">
                <a href="{{ $loginUrl }}" class="block w-full py-3 px-4 bg-[#2f4254] hover:bg-[#15191f] text-white font-medium rounded-lg transition-colors">
                    Back to Sign In
                </a>
                <p class="text-sm text-[#2f4254]">
                    Need help? <a href="#" class="text-[#2a8573] hover:underline">Contact Support</a>
                </p>
            </div>
        </div>

        <!-- Footer -->
        <p class="text-center text-sm text-[#2f4254]/60 mt-6">
            &copy; {{ date('Y') }} {{ $tenantName }}. All rights reserved.
        </p>
    </div>
</body>
</html>
