@php
    use App\Models\CompanySetting;
    use Illuminate\Support\Facades\Storage;
    
    $companySettings = CompanySetting::first();
    $companyName = $companySettings->company_name ?? 'Laravel Starter Kit';
    $logoPath = $companySettings->logo_path ?? null;
@endphp

<div class="flex items-center justify-center rounded-xl bg-accent-content overflow-hidden">
    @php
        $logoDisplayed = false;
    @endphp

    @if ($logoPath)
        @php
            try {
                $logoExists = Storage::exists($logoPath);
            } catch (Exception $e) {
                $logoExists = false;
            }
        @endphp
        
        @if ($logoExists)
            <img 
                src="{{ Storage::url($logoPath) }}" 
                alt="{{ $companyName ?? 'Company Logo' }}"
                class="size-full object-cover"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
            >
            @php $logoDisplayed = true; @endphp
        @endif
    @endif

    {{-- Show fallback SVG if no logo was displayed --}}
    @if (!$logoDisplayed)
<svg 
    xmlns="http://www.w3.org/2000/svg" 
    viewBox="0 0 300 100" 
    class="size-full"
    preserveAspectRatio="xMidYMid meet"
>
    <!-- White Background -->
    <rect width="300" height="100" fill="#ffffff"/>

    <!-- Modern geometric textile pattern -->
    <g fill="none" stroke="#e0e0e0" stroke-width="1.5" opacity="0.4">
        <rect x="10" y="15" width="20" height="20" rx="3"/>
        <rect x="40" y="15" width="20" height="20" rx="3"/>
        <rect x="70" y="15" width="20" height="20" rx="3"/>
        <rect x="25" y="40" width="20" height="20" rx="3"/>
        <rect x="55" y="40" width="20" height="20" rx="3"/>
        <rect x="85" y="40" width="20" height="20" rx="3"/>
    </g>

    <!-- Modern logo mark - abstract textile formation -->
    <g fill="#2d4e8a">
        <path d="M45,35 L55,25 L65,35 L55,45 Z"/>
        <circle cx="55" cy="35" r="8" fill="#e74c3c"/>
    </g>

    <!-- Company Name with modern typography -->
    <text x="95" y="42" font-size="20" fill="#2c3e50" font-weight="700" font-family="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" letter-spacing="1">
        ABBA
    </text>
    <text x="95" y="62" font-size="14" fill="#7f8c8d" font-weight="500" font-family="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" letter-spacing="2">
        TEXTILES
    </text>

    <!-- Subtle accent line -->
    <line x1="95" y1="65" x2="180" y2="65" stroke="#e74c3c" stroke-width="2"/>
</svg>
    @endif
</div>
