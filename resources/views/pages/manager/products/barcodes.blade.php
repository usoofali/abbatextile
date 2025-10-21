<?php

use App\Models\Product;
use App\Models\Shop;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Picqer\Barcode\BarcodeGeneratorSVG;

new #[Layout('components.layouts.app', ['title' => 'Print Barcodes'])] class extends Component {
    public $products = [];
    public $shop;
    public $selectedProducts = [];
    public $barcodeCache = [];

    public function mount(): void
    {
        $user = Auth::user();
        $this->shop = $user->managedShop;

        if (!$this->shop) {
            session()->flash('error', 'No shop assigned to you.');
            return;
        }

        // Get products from session
        $this->selectedProducts = Session::get('barcode_products', []);

        if (empty($this->selectedProducts)) {
            session()->flash('error', 'No products selected for barcode generation.');
            return;
        }

        $this->loadProducts();
        
        // Clear the session data after use
        Session::forget('barcode_products');
    }

    public function loadProducts(): void
    {
        $this->products = $this->shop->products()
            ->whereIn('id', $this->selectedProducts)
            ->get();
    }

    /**
     * Generate barcode as PNG image (base64 encoded)
     */
    public function generateBarcodePng($barcode): string
    {
        if (empty($barcode)) {
            return '';
        }

        // Use cache to avoid regenerating same barcode multiple times
        $cacheKey = 'png_' . $barcode;
        if (isset($this->barcodeCache[$cacheKey])) {
            return $this->barcodeCache[$cacheKey];
        }

        try {
            $generator = new BarcodeGeneratorPNG();
            $barcodeData = $generator->getBarcode(
                $barcode, 
                $generator::TYPE_CODE_128, 
                2, // Width of a single bar
                70 // Height of the barcode
            );
            
            $barcodeImage = 'data:image/png;base64,' . base64_encode($barcodeData);
            $this->barcodeCache[$cacheKey] = $barcodeImage;
            
            return $barcodeImage;
        } catch (Exception $e) {
            logger()->error('Barcode PNG generation failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Generate barcode as SVG (scalable vector graphics)
     */
    public function generateBarcodeSvg($barcode): string
    {
        if (empty($barcode)) {
            return '';
        }

        $cacheKey = 'svg_' . $barcode;
        if (isset($this->barcodeCache[$cacheKey])) {
            return $this->barcodeCache[$cacheKey];
        }

        try {
            $generator = new BarcodeGeneratorSVG();
            $svgContent = $generator->getBarcode(
                $barcode,
                $generator::TYPE_CODE_128,
                2, // Width of a single bar
                70 // Height of the barcode
            );
            
            $this->barcodeCache[$cacheKey] = $svgContent;
            return $svgContent;
        } catch (Exception $e) {
            logger()->error('Barcode SVG generation failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Generate barcode as HTML table (fallback)
     */
    public function generateBarcodeHtml($barcode): string
    {
        if (empty($barcode)) {
            return '';
        }

        $cacheKey = 'html_' . $barcode;
        if (isset($this->barcodeCache[$cacheKey])) {
            return $this->barcodeCache[$cacheKey];
        }

        try {
            $generator = new BarcodeGeneratorHTML();
            $htmlContent = $generator->getBarcode(
                $barcode,
                $generator::TYPE_CODE_128,
                2, // Width factor
                70 // Height
            );
            
            $this->barcodeCache[$cacheKey] = $htmlContent;
            return $htmlContent;
        } catch (Exception $e) {
            logger()->error('Barcode HTML generation failed: ' . $e->getMessage());
            return '<div style="color: red; font-size: 10px;">BARCODE ERROR</div>';
        }
    }

    public function printBarcodes(): void
    {
        $this->dispatch('print-barcodes');
    }

    public function goBack(): void
    {
        $this->redirectRoute('manager.products.index');
    }
}; ?>

<div class="print-barcode-container">
    <!-- Header -->
    <div class="flex items-center justify-between no-print">
        <div>
            <flux:heading size="xl" level="1">Print Product Barcodes</flux:heading>
            <flux:subheading size="lg">{{ $shop?->name ?? 'No shop assigned' }}</flux:subheading>
        </div>
        <div class="flex flex-col sm:flex-row gap-2 max-md:w-full">
            <flux:button icon="arrow-left" variant="outline" wire:click="goBack">
                Back to Products
            </flux:button>
            <flux:button icon="printer" variant="primary" wire:click="printBarcodes">
                Print Barcodes
            </flux:button>
        </div>
    </div>

    @if($shop && count($products) > 0)
        <!-- Print Info -->
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-700 dark:bg-blue-900/20 no-print">
            <div class="flex items-center gap-3">
                <flux:icon name="information-circle" class="size-5 text-blue-600 dark:text-blue-400" />
                <div>
                    <flux:text class="font-medium text-blue-800 dark:text-blue-200">
                        Ready to print {{ count($products) }} product(s) - {{ count($products) * 10 }} total labels
                    </flux:text>
                </div>
            </div>
        </div>

        <!-- Barcodes Container -->
        <div class="barcode-container">
            @foreach($products as $product)
                @for($i = 0; $i < 10; $i++)
                    <div class="barcode-item">
                        <div class="barcode-header">
                            <div class="product-name">{{ Str::limit($product->name, 25) }}</div>
                        </div>
                        
                        @if($product->barcode)
                            <div class="barcode-content">
                                <div class="linear-barcode">
                                    <!-- Try PNG first, fallback to SVG, then HTML -->
                                    @php
                                        $pngBarcode = $this->generateBarcodePng($product->barcode);
                                        $svgBarcode = $this->generateBarcodeSvg($product->barcode);
                                        $htmlBarcode = $this->generateBarcodeHtml($product->barcode);
                                    @endphp

                                    @if($svgBarcode)
                                        <div class="barcode-svg-container">
                                            {!! $svgBarcode !!}
                                        </div>
                                    @elseif($pngBarcode)
                                        <img src="{{ $pngBarcode }}" alt="Barcode {{ $product->barcode }}" class="barcode-image" loading="lazy">
                                    @elseif($htmlBarcode)
                                        <div class="barcode-html-container">
                                            {!! $htmlBarcode !!}
                                        </div>
                                    @else
                                        <div class="barcode-fallback">
                                            <div class="barcode-number">{{ $product->barcode }}</div>
                                            <div class="barcode-error">Image generation failed</div>
                                        </div>
                                    @endif
                                </div>
                                <div class="barcode-number-text">{{ $product->barcode }}</div>
                            </div>
                        @else
                            <div class="no-barcode">
                                <flux:icon name="x-circle" class="size-6 mx-auto mb-2" />
                                NO BARCODE ASSIGNED
                            </div>
                        @endif
                        
                        <div class="barcode-footer">
                            {{ $shop->name }} • {{ now()->format('M j, Y') }}
                        </div>
                    </div>
                @endfor
            @endforeach
        </div>

    @elseif($shop && count($products) === 0 && !empty($selectedProducts))
        <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-6 dark:border-yellow-700 dark:bg-yellow-900/20">
            <div class="flex items-center gap-2">
                <flux:icon name="exclamation-triangle" class="size-5 text-yellow-600 dark:text-yellow-400" />
                <flux:heading size="lg" class="text-yellow-800 dark:text-yellow-200">Products Not Found</flux:heading>
            </div>
            <flux:text class="mt-2 text-yellow-700 dark:text-yellow-300">
                The selected products were not found in your shop. They may have been deleted or transferred.
            </flux:text>
            <div class="mt-4">
                <flux:button icon="arrow-left" variant="outline" wire:click="goBack">
                    Back to Products
                </flux:button>
            </div>
        </div>
    @elseif(!$shop)
        <div class="rounded-xl border border-red-200 bg-red-50 p-6 dark:border-red-700 dark:bg-red-900/20">
            <div class="flex items-center gap-2">
                <flux:icon name="exclamation-triangle" class="size-5 text-red-600 dark:text-red-400" />
                <flux:heading size="lg" class="text-red-800 dark:text-red-200">No Shop Assigned</flux:heading>
            </div>
            <flux:text class="mt-2 text-red-700 dark:text-red-300">
                You don't have a shop assigned to you. Please contact the administrator to assign you to a shop.
            </flux:text>
        </div>
    @else
        <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-6 dark:border-yellow-700 dark:bg-yellow-900/20">
            <div class="flex items-center gap-2">
                <flux:icon name="exclamation-triangle" class="size-5 text-yellow-600 dark:text-yellow-400" />
                <flux:heading size="lg" class="text-yellow-800 dark:text-yellow-200">No Products Selected</flux:heading>
            </div>
            <flux:text class="mt-2 text-yellow-700 dark:text-yellow-300">
                No products were selected for barcode generation. Please go back and select products to generate barcodes.
            </flux:text>
            <div class="mt-4">
                <flux:button icon="arrow-left" variant="outline" wire:click="goBack">
                    Back to Products
                </flux:button>
            </div>
        </div>
    @endif

    <style>
        @media print {
            @page {
                margin: 10mm;
                size: A4;
            }
            body {
                margin: 0;
                padding: 0;
                background: white;
                font-size: 10px;
            }
            .no-print {
                display: none !important;
            }
            .print-barcode-container {
                margin: 0;
                padding: 0;
            }
            .barcode-item {
                border: 1px solid #000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                background: white !important;
            }
            .barcode-image,
            .barcode-svg-container svg {
                filter: contrast(1.2) !important;
            }
        }

        .barcode-container {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            width: 100%;
            margin-top: 20px;
        }

        .barcode-item {
            border: 1px solid #e5e7eb;
            padding: 12px;
            text-align: center;
            page-break-inside: avoid;
            break-inside: avoid;
            height: 170px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border-radius: 4px;
        }

        .barcode-header {
            margin-bottom: 8px;
        }

        .product-name {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 3px;
            word-break: break-word;
            line-height: 1.2;
            color: #1f2937;
        }

        .product-price {
            font-size: 10px;
            color: #6b7280;
            font-weight: 600;
        }

        .unit-type {
            font-size: 9px;
            color: #9ca3af;
            font-style: italic;
        }

        .barcode-content {
            margin: 5px 0;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .linear-barcode {
            margin-bottom: 5px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 65px;
        }

        .barcode-image {
            max-width: 100%;
            height: 50px;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
        }

        .barcode-svg-container {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .barcode-svg-container svg {
            height: 50px;
            max-width: 100%;
        }

        .barcode-html-container {
            display: flex;
            justify-content: center;
            width: 100%;
        }

        .barcode-html-container table {
            border: none !important;
            border-collapse: collapse;
        }

        .barcode-html-container td {
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
        }

        .barcode-number-text {
            font-size: 10px;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #000;
            letter-spacing: 0.5px;
            margin: 3px 0;
        }

        .barcode-label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .no-barcode {
            color: #dc2626;
            font-size: 11px;
            font-weight: 600;
            padding: 15px;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 4px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 65px;
        }

        .barcode-fallback {
            text-align: center;
            padding: 10px;
        }

        .barcode-error {
            font-size: 9px;
            color: #dc2626;
            margin-top: 2px;
        }

        .barcode-footer {
            font-size: 7px;
            color: #9ca3af;
            margin-top: 3px;
        }

        @media (max-width: 1024px) {
            .barcode-container {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 640px) {
            .barcode-container {
                grid-template-columns: 1fr;
            }
            .barcode-item {
                height: 160px;
            }
        }
    </style>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('print-barcodes', () => {
                // Small delay to ensure everything is rendered
                setTimeout(() => {
                    window.print();
                }, 100);
            });
        });

        // Improve print quality
        document.addEventListener('DOMContentLoaded', function() {
            // Force high quality for barcode images when printing
            const style = document.createElement('style');
            style.textContent = `
                @media print {
                    .barcode-image {
                        -webkit-print-color-adjust: exact !important;
                        print-color-adjust: exact !important;
                    }
                }
            `;
            document.head.appendChild(style);
        });
    </script>
</div>