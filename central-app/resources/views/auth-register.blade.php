<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,700|fraunces:600" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        body {
            font-family: 'Space Grotesk', system-ui, sans-serif;
            background:
                radial-gradient(circle at 10% 0%, rgba(209, 75, 56, 0.12), transparent 44%),
                radial-gradient(circle at 90% 100%, rgba(42, 133, 115, 0.16), transparent 40%),
                linear-gradient(180deg, #f6f2e8 0%, #efe9db 100%);
            color: #15191f;
        }

        .display {
            font-family: 'Fraunces', Georgia, serif;
        }

        .strength-bar {
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s ease;
        }

        .strength-weak { background: #d14b38; width: 25%; }
        .strength-fair { background: #d89a2a; width: 50%; }
        .strength-good { background: #2a8573; width: 75%; }
        .strength-strong { background: #1a5c4d; width: 100%; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="w-full max-w-md rounded-2xl border border-[#15191f]/20 bg-white/80 p-6 shadow-[0_10px_40px_rgba(21,25,31,0.12)] backdrop-blur-sm">
        <h1 class="display text-3xl">{{ $title }}</h1>
        <p class="mt-2 text-sm text-[#15191f]/70">{{ $subtitle }}</p>

        <form method="POST" action="{{ $action }}" class="mt-6 space-y-4" id="registerForm">
            @csrf

            {{-- Name Fields --}}
            <div class="grid grid-cols-12 gap-3">
                <label class="col-span-5 block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">First Name</span>
                    <input
                        type="text"
                        name="first_name"
                        required
                        value="{{ old('first_name') }}"
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                        placeholder="John"
                    >
                </label>
                <label class="col-span-2 block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">M.I.</span>
                    <input
                        type="text"
                        name="middle_initial"
                        maxlength="1"
                        value="{{ old('middle_initial') }}"
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254] text-center uppercase"
                        placeholder="A"
                    >
                </label>
                <label class="col-span-5 block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Last Name</span>
                    <input
                        type="text"
                        name="last_name"
                        required
                        value="{{ old('last_name') }}"
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                        placeholder="Doe"
                    >
                </label>
            </div>

            {{-- Email Field --}}
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Email Address</span>
                <input
                    type="email"
                    name="email"
                    required
                    value="{{ old('email') }}"
                    class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                    placeholder="name@example.com"
                >
            </label>

            {{-- Phone Number Field --}}
            <div class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Phone Number</span>
                <div class="flex gap-2">
                    <select
                        name="phone_format"
                        id="phoneFormat"
                        class="w-28 rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-2 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                    >
                        <option value="us" {{ old('phone_format', 'us') === 'us' ? 'selected' : '' }}>🇺🇸 +1</option>
                        <option value="uk" {{ old('phone_format') === 'uk' ? 'selected' : '' }}>🇬🇧 +44</option>
                        <option value="ph" {{ old('phone_format') === 'ph' ? 'selected' : '' }}>🇵🇭 +63</option>
                        <option value="au" {{ old('phone_format') === 'au' ? 'selected' : '' }}>🇦🇺 +61</option>
                        <option value="ca" {{ old('phone_format') === 'ca' ? 'selected' : '' }}>🇨🇦 +1</option>
                        <option value="de" {{ old('phone_format') === 'de' ? 'selected' : '' }}>🇩🇪 +49</option>
                        <option value="fr" {{ old('phone_format') === 'fr' ? 'selected' : '' }}>🇫🇷 +33</option>
                        <option value="jp" {{ old('phone_format') === 'jp' ? 'selected' : '' }}>🇯🇵 +81</option>
                        <option value="other" {{ old('phone_format') === 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                    <input
                        type="tel"
                        name="phone"
                        id="phoneInput"
                        required
                        value="{{ old('phone') }}"
                        class="flex-1 rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                        placeholder="(555) 123-4567"
                    >
                </div>
                <p class="mt-1 text-xs text-[#15191f]/50" id="phoneHint">Format: (555) 123-4567</p>
            </div>

            {{-- Password Field with Strength Indicator --}}
            <div class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Password</span>
                <div class="relative">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        required
                        minlength="8"
                        class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 pr-10 text-sm outline-none transition focus:border-[#2f4254]"
                        placeholder="Create a strong password"
                    >
                    <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-[#15191f]/40 hover:text-[#15191f]/70">
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                {{-- Password Strength Bar --}}
                <div class="mt-2 h-1 w-full rounded-full bg-[#15191f]/10">
                    <div id="strengthBar" class="strength-bar" style="width: 0%;"></div>
                </div>
                <div class="mt-1 flex items-center justify-between">
                    <span id="strengthText" class="text-xs text-[#15191f]/50">Enter a password</span>
                    <span id="strengthLabel" class="text-xs font-medium"></span>
                </div>
                {{-- Password Requirements --}}
                <div class="mt-2 space-y-1 text-xs" id="requirements">
                    <div class="flex items-center gap-2 text-[#15191f]/50" id="req-length">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                        At least 8 characters
                    </div>
                    <div class="flex items-center gap-2 text-[#15191f]/50" id="req-upper">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                        One uppercase letter
                    </div>
                    <div class="flex items-center gap-2 text-[#15191f]/50" id="req-lower">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                        One lowercase letter
                    </div>
                    <div class="flex items-center gap-2 text-[#15191f]/50" id="req-number">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                        One number
                    </div>
                    <div class="flex items-center gap-2 text-[#15191f]/50" id="req-special">
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-current"></span>
                        One special character (!@#$%^&*)
                    </div>
                </div>
            </div>

            {{-- Confirm Password Field --}}
            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Confirm Password</span>
                <input
                    type="password"
                    name="password_confirmation"
                    id="passwordConfirm"
                    required
                    class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                    placeholder="Confirm your password"
                >
                <p id="matchMessage" class="mt-1 text-xs hidden"></p>
            </label>

            {{-- Role Selection --}}
            <div class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Role</span>
                <select
                    name="role"
                    id="roleSelect"
                    required
                    class="w-full rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] px-3 py-2 text-sm outline-none transition focus:border-[#2f4254]"
                >
                    <option value="staff" {{ old('role', 'staff') === 'staff' ? 'selected' : '' }}>Staff</option>
                    <option value="cashier" {{ old('role') === 'cashier' ? 'selected' : '' }}>Cashier</option>
                    <option value="manager" {{ old('role') === 'manager' ? 'selected' : '' }}>Manager</option>
                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin (Requires Approval)</option>
                </select>
                <p id="roleHint" class="mt-1 text-xs text-[#15191f]/50">Staff, Cashier, and Manager accounts are activated immediately.</p>
                <div id="adminWarning" class="mt-2 hidden rounded-lg border border-[#d89a2a]/40 bg-[#d89a2a]/10 px-3 py-2 text-xs text-[#8b6914]">
                    <strong>Note:</strong> Admin accounts require approval from an existing tenant administrator. You'll be notified once approved.
                </div>
            </div>

            @if ($errors->any())
                <div class="rounded-lg border border-[#d14b38]/40 bg-[#d14b38]/10 px-3 py-2 text-sm text-[#7a2e23]">
                    {{ $errors->first() }}
                </div>
            @endif

            <button
                type="submit"
                id="submitBtn"
                class="w-full rounded-lg border border-[#15191f]/25 bg-[#15191f] px-4 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#f6f2e8] transition hover:bg-[#2f4254] disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {{ $submitLabel }}
            </button>

            <p class="text-center text-sm text-[#15191f]/60">
                Already have an account?
                <a href="{{ $loginUrl ?? '#' }}" class="font-medium text-[#2f4254] hover:underline">Sign in</a>
            </p>
        </form>
    </div>

    {{-- OTP Verification Modal --}}
    <div id="otpModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="w-full max-w-sm rounded-2xl border border-[#15191f]/20 bg-white p-6 shadow-2xl">
            <h2 class="display text-2xl">Verify Your Email</h2>
            <p class="mt-2 text-sm text-[#15191f]/70">We've sent a 6-digit code to <span id="otpEmail" class="font-medium"></span></p>

            <div class="mt-6">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f]/65">Enter Code</span>
                    <div class="flex gap-2 justify-center" id="otpInputs">
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] text-center text-xl font-bold outline-none transition focus:border-[#2f4254]" data-index="0">
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] text-center text-xl font-bold outline-none transition focus:border-[#2f4254]" data-index="1">
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] text-center text-xl font-bold outline-none transition focus:border-[#2f4254]" data-index="2">
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] text-center text-xl font-bold outline-none transition focus:border-[#2f4254]" data-index="3">
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] text-center text-xl font-bold outline-none transition focus:border-[#2f4254]" data-index="4">
                        <input type="text" maxlength="1" class="otp-input w-12 h-12 rounded-lg border border-[#15191f]/25 bg-[#fdfaf3] text-center text-xl font-bold outline-none transition focus:border-[#2f4254]" data-index="5">
                    </div>
                    <input type="hidden" name="otp_code" id="otpCode">
                </label>
                <p id="otpError" class="mt-2 text-xs text-[#d14b38] hidden"></p>
            </div>

            <div class="mt-4 flex gap-2">
                <button
                    type="button"
                    id="cancelOtp"
                    class="flex-1 rounded-lg border border-[#15191f]/25 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#15191f] transition hover:bg-[#f6f2e8]"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    id="verifyOtp"
                    class="flex-1 rounded-lg border border-[#15191f]/25 bg-[#15191f] px-4 py-2 text-xs font-semibold uppercase tracking-[0.12em] text-[#f6f2e8] transition hover:bg-[#2f4254] disabled:opacity-50"
                    disabled
                >
                    Verify
                </button>
            </div>

            <div class="mt-4 text-center">
                <p class="text-xs text-[#15191f]/50">
                    Didn't receive the code?
                    <button type="button" id="resendOtp" class="font-medium text-[#2f4254] hover:underline disabled:opacity-50" disabled>
                        Resend (<span id="resendTimer">60</span>s)
                    </button>
                </p>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const password = document.getElementById('password');
            const passwordConfirm = document.getElementById('passwordConfirm');
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            const strengthLabel = document.getElementById('strengthLabel');
            const togglePassword = document.getElementById('togglePassword');
            const eyeIcon = document.getElementById('eyeIcon');
            const matchMessage = document.getElementById('matchMessage');
            const submitBtn = document.getElementById('submitBtn');
            const phoneFormat = document.getElementById('phoneFormat');
            const phoneInput = document.getElementById('phoneInput');
            const phoneHint = document.getElementById('phoneHint');

            // Phone format patterns
            const phonePatterns = {
                us: { placeholder: '(555) 123-4567', hint: 'Format: (555) 123-4567' },
                uk: { placeholder: '7911 123456', hint: 'Format: 7911 123456' },
                ph: { placeholder: '917 123 4567', hint: 'Format: 917 123 4567' },
                au: { placeholder: '412 345 678', hint: 'Format: 412 345 678' },
                ca: { placeholder: '(555) 123-4567', hint: 'Format: (555) 123-4567' },
                de: { placeholder: '170 1234567', hint: 'Format: 170 1234567' },
                fr: { placeholder: '6 12 34 56 78', hint: 'Format: 6 12 34 56 78' },
                jp: { placeholder: '90-1234-5678', hint: 'Format: 90-1234-5678' },
                other: { placeholder: 'Enter phone number', hint: 'Enter your phone number' }
            };

            // Update phone input based on format selection
            phoneFormat.addEventListener('change', function() {
                const format = this.value;
                const pattern = phonePatterns[format];
                phoneInput.placeholder = pattern.placeholder;
                phoneHint.textContent = pattern.hint;
            });

            // Toggle password visibility
            togglePassword.addEventListener('click', function() {
                const type = password.type === 'password' ? 'text' : 'password';
                password.type = type;
                eyeIcon.innerHTML = type === 'password' 
                    ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />'
                    : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
            });

            // Password strength checker
            function checkStrength(pwd) {
                let score = 0;
                const requirements = {
                    length: pwd.length >= 8,
                    upper: /[A-Z]/.test(pwd),
                    lower: /[a-z]/.test(pwd),
                    number: /[0-9]/.test(pwd),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(pwd)
                };

                // Update requirement indicators
                Object.keys(requirements).forEach(key => {
                    const el = document.getElementById('req-' + key);
                    if (requirements[key]) {
                        el.classList.remove('text-[#15191f]/50');
                        el.classList.add('text-[#2a8573]');
                        score++;
                    } else {
                        el.classList.remove('text-[#2a8573]');
                        el.classList.add('text-[#15191f]/50');
                    }
                });

                return { score, requirements };
            }

            function updateStrengthUI(score) {
                const levels = [
                    { class: '', text: 'Enter a password', label: '', color: '' },
                    { class: 'strength-weak', text: 'Password is too weak', label: 'Weak', color: '#d14b38' },
                    { class: 'strength-fair', text: 'Password could be stronger', label: 'Fair', color: '#d89a2a' },
                    { class: 'strength-good', text: 'Password is good', label: 'Good', color: '#2a8573' },
                    { class: 'strength-good', text: 'Password is good', label: 'Good', color: '#2a8573' },
                    { class: 'strength-strong', text: 'Password is strong!', label: 'Strong', color: '#1a5c4d' }
                ];

                const level = levels[score];
                strengthBar.className = 'strength-bar ' + level.class;
                strengthText.textContent = level.text;
                strengthLabel.textContent = level.label;
                strengthLabel.style.color = level.color;
            }

            password.addEventListener('input', function() {
                const result = checkStrength(this.value);
                updateStrengthUI(result.score);
                checkMatch();
            });

            // Check password match
            function checkMatch() {
                if (passwordConfirm.value === '') {
                    matchMessage.classList.add('hidden');
                    return;
                }

                matchMessage.classList.remove('hidden');
                if (password.value === passwordConfirm.value) {
                    matchMessage.textContent = '✓ Passwords match';
                    matchMessage.classList.remove('text-[#d14b38]');
                    matchMessage.classList.add('text-[#2a8573]');
                } else {
                    matchMessage.textContent = '✗ Passwords do not match';
                    matchMessage.classList.remove('text-[#2a8573]');
                    matchMessage.classList.add('text-[#d14b38]');
                }
            }

            passwordConfirm.addEventListener('input', checkMatch);

            // Role selection handling
            const roleSelect = document.getElementById('roleSelect');
            const roleHint = document.getElementById('roleHint');
            const adminWarning = document.getElementById('adminWarning');

            roleSelect.addEventListener('change', function() {
                if (this.value === 'admin') {
                    adminWarning.classList.remove('hidden');
                    roleHint.classList.add('hidden');
                } else {
                    adminWarning.classList.add('hidden');
                    roleHint.classList.remove('hidden');
                }
            });

            // OTP Modal Elements
            const otpModal = document.getElementById('otpModal');
            const otpEmail = document.getElementById('otpEmail');
            const otpInputs = document.querySelectorAll('.otp-input');
            const otpCode = document.getElementById('otpCode');
            const otpError = document.getElementById('otpError');
            const verifyOtp = document.getElementById('verifyOtp');
            const cancelOtp = document.getElementById('cancelOtp');
            const resendOtp = document.getElementById('resendOtp');
            const resendTimer = document.getElementById('resendTimer');
            const registerForm = document.getElementById('registerForm');

            let generatedOtp = '';
            let resendCountdown = 60;
            let countdownInterval = null;
            let formData = null;

            // Generate 6-digit OTP
            function generateOtp() {
                return Math.floor(100000 + Math.random() * 900000).toString();
            }

            // Show OTP modal
            function showOtpModal(email) {
                generatedOtp = generateOtp();
                otpEmail.textContent = email;
                otpModal.classList.remove('hidden');
                otpModal.classList.add('flex');
                otpInputs[0].focus();
                
                // Log OTP to console for demo (in production, send via email/SMS)
                console.log('%c[OTP CODE]: ' + generatedOtp, 'color: #2a8573; font-size: 18px; font-weight: bold;');
                alert('Your OTP code is: ' + generatedOtp + '\n\n(In production, this would be sent to your email)');
                
                startResendTimer();
            }

            // Hide OTP modal
            function hideOtpModal() {
                otpModal.classList.add('hidden');
                otpModal.classList.remove('flex');
                otpInputs.forEach(input => input.value = '');
                otpError.classList.add('hidden');
                verifyOtp.disabled = true;
                if (countdownInterval) clearInterval(countdownInterval);
            }

            // Start resend timer
            function startResendTimer() {
                resendCountdown = 60;
                resendOtp.disabled = true;
                resendTimer.textContent = resendCountdown;
                
                if (countdownInterval) clearInterval(countdownInterval);
                
                countdownInterval = setInterval(() => {
                    resendCountdown--;
                    resendTimer.textContent = resendCountdown;
                    
                    if (resendCountdown <= 0) {
                        clearInterval(countdownInterval);
                        resendOtp.disabled = false;
                        resendOtp.innerHTML = 'Resend Code';
                    }
                }, 1000);
            }

            // OTP input handling
            otpInputs.forEach((input, index) => {
                input.addEventListener('input', function(e) {
                    const value = e.target.value.replace(/[^0-9]/g, '');
                    e.target.value = value;
                    
                    if (value && index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                    
                    // Check if all inputs are filled
                    const allFilled = Array.from(otpInputs).every(inp => inp.value.length === 1);
                    verifyOtp.disabled = !allFilled;
                    
                    if (allFilled) {
                        otpCode.value = Array.from(otpInputs).map(inp => inp.value).join('');
                    }
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        otpInputs[index - 1].focus();
                    }
                });

                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasteData = e.clipboardData.getData('text').replace(/[^0-9]/g, '').slice(0, 6);
                    pasteData.split('').forEach((char, i) => {
                        if (otpInputs[i]) otpInputs[i].value = char;
                    });
                    if (pasteData.length === 6) {
                        otpCode.value = pasteData;
                        verifyOtp.disabled = false;
                        otpInputs[5].focus();
                    }
                });
            });

            // Cancel OTP
            cancelOtp.addEventListener('click', hideOtpModal);

            // Resend OTP
            resendOtp.addEventListener('click', function() {
                generatedOtp = generateOtp();
                console.log('%c[NEW OTP CODE]: ' + generatedOtp, 'color: #2a8573; font-size: 18px; font-weight: bold;');
                alert('New OTP code: ' + generatedOtp + '\n\n(In production, this would be sent to your email)');
                startResendTimer();
                otpError.classList.add('hidden');
            });

            // Verify OTP
            verifyOtp.addEventListener('click', function() {
                const enteredOtp = otpCode.value;
                
                if (enteredOtp === generatedOtp) {
                    // OTP verified - submit the form
                    hideOtpModal();
                    
                    // Create hidden form and submit
                    const hiddenForm = document.createElement('form');
                    hiddenForm.method = 'POST';
                    hiddenForm.action = registerForm.action;
                    hiddenForm.style.display = 'none';
                    
                    // Add CSRF token
                    const csrfInput = document.createElement('input');
                    csrfInput.type = 'hidden';
                    csrfInput.name = '_token';
                    csrfInput.value = document.querySelector('input[name="_token"]').value;
                    hiddenForm.appendChild(csrfInput);
                    
                    // Add form data
                    for (const [key, value] of formData.entries()) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        hiddenForm.appendChild(input);
                    }
                    
                    // Add OTP verified flag
                    const otpVerified = document.createElement('input');
                    otpVerified.type = 'hidden';
                    otpVerified.name = 'otp_verified';
                    otpVerified.value = '1';
                    hiddenForm.appendChild(otpVerified);
                    
                    document.body.appendChild(hiddenForm);
                    hiddenForm.submit();
                } else {
                    otpError.textContent = 'Invalid code. Please try again.';
                    otpError.classList.remove('hidden');
                    otpInputs.forEach(input => {
                        input.classList.add('border-[#d14b38]');
                        setTimeout(() => input.classList.remove('border-[#d14b38]'), 2000);
                    });
                }
            });

            // Form validation before submit - show OTP modal
            registerForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const result = checkStrength(password.value);
                if (result.score < 3) {
                    alert('Please create a stronger password that meets at least 3 requirements.');
                    return;
                }

                if (password.value !== passwordConfirm.value) {
                    alert('Passwords do not match.');
                    return;
                }

                // Store form data and show OTP modal
                formData = new FormData(registerForm);
                const email = formData.get('email');
                showOtpModal(email);
            });
        });
    </script>
</body>
</html>
