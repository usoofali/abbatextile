@php
    use App\Models\CompanySetting;
    use Illuminate\Support\Facades\Storage;
    
    $companySettings = CompanySetting::first();
    $companyName = $companySettings->company_name ?? 'Laravel Starter Kit';
    $logoPath = $companySettings->logo_path ?? null;
@endphp

<div class="flex items-center justify-center rounded-xl bg-accent-content overflow-hidden">
    <img 
        src="{{ Storage::url($logoPath) }}" 
        alt="{{ $companyName }}"
        class="size-full object-cover"
    >
</div>
