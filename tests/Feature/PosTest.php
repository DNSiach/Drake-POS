<?php

namespace Tests\Feature;

use Tests\TestCase;

class PosTest extends TestCase
{
    public function test_inventory_can_be_added_with_cost_and_retail_prices(): void
    {
        $response = $this->withSession(['pos_products' => [], 'pos_sales' => []])->post('/inventory', [
            'name' => 'Bread', 'category' => 'Bakery',
            'cost_price' => 8, 'retail_price' => 12, 'stock' => 10,
        ]);

        $response->assertRedirect('/inventory');
        $this->assertSame(10, session('pos_products.0.stock'));
        $this->assertSame(8.0, session('pos_products.0.cost_price'));
        $this->assertSame(12.0, session('pos_products.0.retail_price'));
    }

    public function test_recording_a_sale_reduces_stock_and_records_profit(): void
    {
        $response = $this->withSession([
            'pos_products' => [[
                'id' => 'butter', 'name' => 'Butter', 'category' => 'Dairy',
                'cost_price' => 28.0, 'retail_price' => 38.0, 'stock' => 18,
            ]],
            'pos_sales' => [],
        ])->post('/sales', ['product_id' => 'butter', 'quantity' => 3]);

        $response->assertRedirect('/sales');
        $this->assertSame(15, session('pos_products.0.stock'));
        $this->assertSame(114.0, session('pos_sales.0.revenue'));
        $this->assertSame(30.0, session('pos_sales.0.profit'));
    }
}
