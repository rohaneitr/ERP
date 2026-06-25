<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Currency;
use App\Barcode;
use App\System;
use Spatie\Permission\Models\Permission;

class SeedEssentialData extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Seed Currencies
        if (Currency::count() === 0) {
            $seeder = new \Database\Seeders\CurrenciesTableSeeder();
            $seeder->run();
        }

        // 2. Seed Barcodes
        if (Barcode::count() === 0) {
            $seeder = new \Database\Seeders\BarcodesTableSeeder();
            $seeder->run();
        }

        // 3. Seed Spatie Permissions (idempotent firstOrCreate)
        $permissions = [
            'user.view', 'user.create', 'user.update', 'user.delete',
            'supplier.view', 'supplier.create', 'supplier.update', 'supplier.delete',
            'customer.view', 'customer.create', 'customer.update', 'customer.delete',
            'product.view', 'product.create', 'product.update', 'product.delete',
            'purchase.view', 'purchase.create', 'purchase.update', 'purchase.delete',
            'sell.view', 'sell.create', 'sell.update', 'sell.delete',
            'purchase_n_sell_report.view', 'contacts_report.view', 'stock_report.view',
            'tax_report.view', 'trending_product_report.view', 'register_report.view',
            'sales_representative.view', 'expense_report.view',
            'business_settings.access', 'barcode_settings.access', 'invoice_settings.access',
            'brand.view', 'brand.create', 'brand.update', 'brand.delete',
            'tax_rate.view', 'tax_rate.create', 'tax_rate.update', 'tax_rate.delete',
            'unit.view', 'unit.create', 'unit.update', 'unit.delete',
            'category.view', 'category.create', 'category.update', 'category.delete',
            'expense.access', 'access_all_locations', 'dashboard.data'
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // 4. Ensure System app_currency_id is set to a valid Currency ID
        $appCurrencySetting = System::where('key', 'app_currency_id')->first();
        if (!$appCurrencySetting) {
            System::addProperty('app_currency_id', '2'); // default to USD
        } else {
            // If the value points to a non-existent currency, reset to 2 (USD)
            if (empty($appCurrencySetting->value) || !Currency::where('id', $appCurrencySetting->value)->exists()) {
                $appCurrencySetting->update(['value' => '2']);
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Seeding migrations do not need rollback logic
    }
}
