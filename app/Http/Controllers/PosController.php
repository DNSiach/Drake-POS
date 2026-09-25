<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PosController extends Controller
{
    public function dashboard(Request $request): View
    {
        $this->ensureStore($request);
        $period = in_array($request->query('period'), ['day', 'month', 'year'], true) ? $request->query('period') : 'month';
        $sales = collect($request->session()->get('pos_sales', []))->filter(fn (array $sale): bool => $this->inPeriod($sale['recorded_at'], $period));
        $products = collect($request->session()->get('pos_products', []));

        return view('home', [
            'page' => 'dashboard', 'period' => $period, 'products' => $products->all(),
            'sales' => $sales->values()->all(), 'allSales' => $request->session()->get('pos_sales', []),
            'metrics' => [
                'revenue' => $sales->sum('revenue'), 'profit' => $sales->sum('profit'),
                'units_sold' => $sales->sum('quantity'), 'asset_value' => $products->sum(fn (array $product): float => $product['cost_price'] * $product['stock']),
            ],
        ]);
    }

    public function inventory(Request $request): View
    {
        $this->ensureStore($request);

        return view('home', ['page' => 'inventory', 'products' => $request->session()->get('pos_products', []), 'sales' => [], 'allSales' => $request->session()->get('pos_sales', []), 'period' => 'month', 'metrics' => []]);
    }

    public function sales(Request $request): View
    {
        $this->ensureStore($request);

        return view('home', ['page' => 'sales', 'products' => $request->session()->get('pos_products', []), 'sales' => $request->session()->get('pos_sales', []), 'allSales' => $request->session()->get('pos_sales', []), 'period' => 'month', 'metrics' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedProduct($request);
        $products = $request->session()->get('pos_products', []);
        $products[] = ['id' => (string) Str::uuid(), ...$data];
        $request->session()->put('pos_products', $products);

        return to_route('inventory.index')->with('success', 'Stock added to inventory.');
    }

    public function update(Request $request, string $product): RedirectResponse
    {
        $data = $this->validatedProduct($request);
        $products = $request->session()->get('pos_products', []);

        foreach ($products as $index => $item) {
            if ($item['id'] === $product) {
                $products[$index] = ['id' => $product, ...$data];
                $request->session()->put('pos_products', $products);

                return to_route('inventory.index')->with('success', 'Inventory item updated.');
            }
        }

        return to_route('inventory.index')->with('error', 'Inventory item could not be found.');
    }

    public function destroy(Request $request, string $product): RedirectResponse
    {
        $products = array_values(array_filter($request->session()->get('pos_products', []), fn (array $item): bool => $item['id'] !== $product));
        $request->session()->put('pos_products', $products);

        return to_route('inventory.index')->with('success', 'Inventory item removed.');
    }

    public function sell(Request $request): RedirectResponse
    {
        $data = $request->validate(['product_id' => ['required', 'string'], 'quantity' => ['required', 'integer', 'min:1']]);
        $products = $request->session()->get('pos_products', []);

        foreach ($products as $index => $product) {
            if ($product['id'] !== $data['product_id']) {
                continue;
            }
            if ($product['stock'] < $data['quantity']) {
                return to_route('sales.index')->with('error', 'Not enough stock for this sale.');
            }

            $quantity = (int) $data['quantity'];
            $products[$index]['stock'] -= $quantity;
            $sales = $request->session()->get('pos_sales', []);
            array_unshift($sales, [
                'id' => (string) Str::uuid(), 'product' => $product['name'], 'product_id' => $product['id'],
                'quantity' => $quantity, 'revenue' => $product['retail_price'] * $quantity,
                'profit' => ($product['retail_price'] - $product['cost_price']) * $quantity,
                'recorded_at' => now()->toIso8601String(), 'date' => now()->format('d M Y, g:i A'),
            ]);
            $request->session()->put('pos_products', $products);
            $request->session()->put('pos_sales', array_slice($sales, 0, 100));

            return to_route('sales.index')->with('success', 'Sale recorded. Inventory reduced by '.$quantity.' '.$product['name'].'.');
        }

        return to_route('sales.index')->with('error', 'Select a valid product.');
    }

    private function validatedProduct(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'], 'sku' => ['required', 'string', 'max:30'], 'category' => ['required', 'string', 'max:40'],
            'cost_price' => ['required', 'numeric', 'min:0'], 'retail_price' => ['required', 'numeric', 'gte:cost_price'], 'stock' => ['required', 'integer', 'min:0'],
        ]);
        $data['cost_price'] = (float) $data['cost_price'];
        $data['retail_price'] = (float) $data['retail_price'];

        return $data;
    }

    private function ensureStore(Request $request): void
    {
        $products = $request->session()->get('pos_products', []);

        if (! $request->session()->has('pos_products') || ! isset($products[0]['cost_price'])) {
            $request->session()->put('pos_products', $this->starterProducts());
        }
    }

    private function inPeriod(string $date, string $period): bool
    {
        $date = Carbon::parse($date);
        return match ($period) {
            'day' => $date->isToday(), 'year' => $date->isSameYear(now()), default => $date->isSameMonth(now()),
        };
    }

    private function starterProducts(): array
    {
        return [
            ['id' => 'butter', 'name' => 'Butter', 'sku' => 'GRO-001', 'category' => 'Dairy', 'cost_price' => 28.00, 'retail_price' => 38.00, 'stock' => 18],
            ['id' => 'soft-drinks', 'name' => 'Soft Drinks', 'sku' => 'BEV-002', 'category' => 'Beverages', 'cost_price' => 12.00, 'retail_price' => 18.00, 'stock' => 36],
        ];
    }
}
