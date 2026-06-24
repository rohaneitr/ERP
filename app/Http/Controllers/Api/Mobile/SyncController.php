<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\{Product, Contact, Transaction, TaxRate, Unit, Business};
use App\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncController extends Controller
{
    // ─── PULL Endpoints ───────────────────────────────────────────────────────

    /**
     * GET /api/mobile/sync/products?cursor=&location_id=
     *
     * Returns products updated after `cursor` (Unix ms timestamp).
     * Includes variations and stock levels for the given location.
     */
    public function pullProducts(Request $request): JsonResponse
    {
        $cursor     = (int) $request->query('cursor', 0);
        $locationId = (int) $request->query('location_id', $request->user()->location_id ?? 1);
        $cursorDate = Carbon::createFromTimestampMs($cursor);

        $products = Product::with([
            'variations',
            'variations.group_prices',
            'variations.variation_location_details' => function ($q) use ($locationId) {
                $q->where('location_id', $locationId);
            },
        ])
        ->where('business_id', $request->user()->business_id)
        ->where('updated_at', '>', $cursorDate)
        ->orderBy('updated_at')
        ->limit(500)
        ->get();

        $records = $products->map(function (Product $p) {
            return [
                'id'            => $p->id,
                'name'          => $p->name,
                'sku'           => $p->sku,
                'barcode'       => $p->barcode,
                'type'          => $p->type,
                'category_id'   => $p->category_id,
                'brand_id'      => $p->brand_id,
                'unit_id'       => $p->unit_id,
                'image_url'     => $p->image ? asset('uploads/img/' . $p->image) : null,
                'alert_quantity'=> $p->alert_quantity,
                'is_inactive'   => (bool) $p->is_inactive,
                'updated_at'    => $p->updated_at->toIso8601String(),
                'variations'    => $p->variations->map(function ($v) {
                    $stock = $v->variation_location_details->first();
                    return [
                        'id'                  => $v->id,
                        'name'                => $v->name,
                        'sub_sku'             => $v->sub_sku,
                        'default_sell_price'  => (float) $v->default_sell_price,
                        'dpp_inc_tax'         => (float) ($v->dpp_inc_tax ?? 0),
                        'qty_available'       => (float) ($stock?->qty_available ?? 0),
                        'group_prices'        => $v->group_prices->map(function($gp) {
                            return [
                                'price_group_id' => $gp->price_group_id,
                                'price_inc_tax'  => (float) $gp->price_inc_tax,
                                'price_type'     => $gp->price_type,
                            ];
                        })->toArray(),
                    ];
                }),
            ];
        });

        $nextCursor = $products->isNotEmpty()
            ? $products->last()->updated_at->getTimestampMs()
            : $cursor;

        return response()->json([
            'records'     => $records,
            'next_cursor' => (string) $nextCursor,
            'count'       => $records->count(),
        ]);
    }

    /**
     * GET /api/mobile/sync/contacts?cursor=
     */
    public function pullContacts(Request $request): JsonResponse
    {
        $cursor     = (int) $request->query('cursor', 0);
        $cursorDate = Carbon::createFromTimestampMs($cursor);

        $contacts = \App\Contact::where('business_id', $request->user()->business_id)
            ->where('updated_at', '>', $cursorDate)
            ->orderBy('updated_at')
            ->limit(500)
            ->get(['id', 'name', 'type', 'mobile', 'email', 'balance', 'credit_limit', 'customer_group_id', 'updated_at']);

        $nextCursor = $contacts->isNotEmpty()
            ? $contacts->last()->updated_at->getTimestampMs()
            : $cursor;

        return response()->json([
            'records'     => $contacts,
            'next_cursor' => (string) $nextCursor,
        ]);
    }

    /**
     * GET /api/mobile/sync/reference-data?cursor=
     *
     * Returns tax rates, categories, and units in a single call.
     * These change rarely, so we bundle them.
     */
    public function pullReferenceData(Request $request): JsonResponse
    {
        $businessId = $request->user()->business_id;

        $taxRates = TaxRate::where('business_id', $businessId)
            ->select('id', 'name', 'amount')
            ->get()
            ->map(fn($t) => array_merge($t->toArray(), [
                'type' => 'percentage',
                '_type' => 'tax_rate'
            ]));

        $categories = Category::where('business_id', $businessId)
            ->select('id', 'name', 'parent_id')
            ->get()
            ->map(fn($c) => array_merge($c->toArray(), ['_type' => 'category']));

        $units = Unit::where('business_id', $businessId)
            ->select('id', 'actual_name', 'allow_decimal')
            ->get()
            ->map(fn($u) => array_merge($u->toArray(), ['_type' => 'unit']));

        $priceGroups = \App\SellingPriceGroup::where('business_id', $businessId)
            ->where('is_active', 1)
            ->select('id', 'name', 'description')
            ->get()
            ->map(fn($pg) => array_merge($pg->toArray(), ['_type' => 'selling_price_group']));

        $expenseCategories = \App\ExpenseCategory::where('business_id', $businessId)
            ->select('id', 'name', 'code', 'parent_id')
            ->get()
            ->map(fn($ec) => array_merge($ec->toArray(), ['_type' => 'expense_category']));

        $customerGroups = \App\CustomerGroup::where('business_id', $businessId)
            ->select('id', 'name', 'amount', 'price_calculation_type', 'selling_price_group_id')
            ->get()
            ->map(fn($cg) => array_merge($cg->toArray(), ['_type' => 'customer_group']));

        $records = $taxRates
            ->concat($categories)
            ->concat($units)
            ->concat($priceGroups)
            ->concat($expenseCategories)
            ->concat($customerGroups)
            ->values();

        return response()->json([
            'records'     => $records,
            'next_cursor' => (string) now()->getTimestampMs(),
        ]);
    }

    /**
     * GET /api/mobile/sync/transactions?cursor=&type=sell
     */
    public function pullTransactions(Request $request): JsonResponse
    {
        $cursor     = (int) $request->query('cursor', 0);
        $type       = $request->query('type', 'sell');
        $cursorDate = Carbon::createFromTimestampMs($cursor);
        $since      = now()->subDays(90);

        $transactions = Transaction::where('business_id', $request->user()->business_id)
            ->when($type === 'purchase', function($q) {
                $q->with(['purchase_lines' => function($sq) {
                    $sq->select('id', 'transaction_id', 'product_id', 'variation_id', 'quantity', 'quantity_returned', 'purchase_price as unit_price', 'tax_id', 'item_tax as tax_amount');
                }]);
            })
            ->when($type === 'sell', function($q) {
                $q->with(['sell_lines' => function($sq) {
                    $sq->select('id', 'transaction_id', 'product_id', 'variation_id', 'quantity', 'quantity_returned', 'unit_price', 'tax_id', 'item_tax as tax_amount');
                }]);
            })
            ->where('type', $type)
            ->where('updated_at', '>', $cursorDate)
            ->where('transaction_date', '>=', $since)
            ->orderBy('updated_at')
            ->limit(200)
            ->get(['id', 'invoice_no', 'ref_no', 'contact_id', 'location_id', 'type', 'return_parent_id',
                   'transaction_date', 'final_total', 'payment_status', 'updated_at']);

        $nextCursor = $transactions->isNotEmpty()
            ? $transactions->last()->updated_at->getTimestampMs()
            : $cursor;

        return response()->json([
            'records'     => $transactions,
            'next_cursor' => (string) $nextCursor,
        ]);
    }

    /**
     * GET /api/mobile/sync/settings
     */
    public function pullSettings(Request $request): JsonResponse
    {
        $business = Business::with('currency')->find($request->user()->business_id);

        return response()->json([
            'records'     => [['_type' => 'business_settings', 'data' => [
                'name'               => $business->name,
                'currency_symbol'    => $business->currency?->symbol ?? '',
                'currency_precision' => $business->currency_precision ?? 2,
                'quantity_precision' => $business->quantity_precision ?? 2,
                'date_format'        => $business->date_format ?? 'd/m/Y',
                'time_format'        => $business->time_format ?? 'h:i A',
            ]]],
            'next_cursor' => (string) now()->getTimestampMs(),
        ]);
    }

    // ─── PUSH Endpoint ────────────────────────────────────────────────────────

    /**
     * POST /api/mobile/sync/push
     *
     * Accepts a single offline action from the mobile sync queue.
     * Uses local_uuid for idempotency — if already processed, returns 200.
     */
    public function push(Request $request): JsonResponse
    {
        $request->validate([
            'action'      => ['required', 'string'],
            'entity_type' => ['required', 'string'],
            'local_uuid'  => ['required', 'string', 'uuid'],
            'payload'     => ['required', 'array'],
        ]);

        $action    = $request->input('action');
        $localUuid = $request->input('local_uuid');
        $payload   = $request->input('payload');

        Log::info('Mobile sync push', ['action' => $action, 'uuid' => $localUuid]);

        try {
            $result = match ($action) {
                'sale_create'         => $this->processSaleCreate($request->user(), $payload, $localUuid),
                'cash_register_open'  => $this->processCashRegisterOpen($request->user(), $payload),
                'cash_register_close' => $this->processCashRegisterClose($request->user(), $payload),
                'stock_adjustment'    => $this->processStockAdjustment($request->user(), $payload, $localUuid),
                'purchase_create'     => $this->processPurchaseCreate($request->user(), $payload, $localUuid),
                'contact_create'      => $this->processContactCreate($request->user(), $payload, $localUuid),
                'contact_update'      => $this->processContactUpdate($request->user(), $payload),
                'expense_create'      => $this->processExpenseCreate($request->user(), $payload, $localUuid),
                'purchase_return_create' => $this->processPurchaseReturnCreate($request->user(), $payload, $localUuid),
                'product_create'      => $this->processProductCreate($request->user(), $payload, $localUuid),
                default               => ['server_id' => null, 'skipped' => true],
            };

            return response()->json(['success' => true, ...$result]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => implode(', ', array_merge(...array_values($e->errors()))),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Mobile sync push failed', ['error' => $e->getMessage(), 'action' => $action]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ─── Push Action Handlers ─────────────────────────────────────────────────

    private function processSaleCreate($user, array $payload, string $localUuid): array
    {
        // Idempotency: check if this sale was already synced
        $existing = Transaction::where('invoice_no', 'like', '%' . substr($localUuid, 0, 8) . '%')
                               ->orWhere('ref_no', $localUuid)
                               ->first();
        if ($existing) {
            return ['server_id' => $existing->id, 'duplicate' => true];
        }

        return DB::transaction(function () use ($user, $payload, $localUuid) {
            /** @var \App\Utils\TransactionUtil $transactionUtil */
            $transactionUtil = app(\App\Utils\TransactionUtil::class);

            // Build the sell data in the format the existing controller expects
            $sellData = [
                'business_id'        => $user->business_id,
                'location_id'        => $payload['location_id'],
                'contact_id'         => $payload['contact_id'] ?? null,
                'transaction_date'   => $payload['transaction_date'],
                'invoice_scheme_id'  => null,
                'type'               => 'sell',
                'status'             => isset($payload['status']) && $payload['status'] == 'quotation' ? 'draft' : ($payload['status'] ?? 'final'),
                'is_quotation'       => isset($payload['status']) && $payload['status'] == 'quotation' ? 1 : 0,
                'sub_status'         => isset($payload['status']) && $payload['status'] == 'quotation' ? 'quotation' : null,
                'payment_status'     => $payload['payment_status'],
                'additional_notes'   => $payload['notes'] ?? null,
                'final_total'        => $payload['final_total'],
                'total_before_tax'   => $payload['total_before_tax'],
                'tax_amount'         => $payload['tax_amount'],
                'discount_amount'    => $payload['discount_amount'] ?? 0,
                'is_created_from_api'=> 1,
                'ref_no'             => $localUuid, // store local UUID for idempotency
            ];

            // Create the transaction using the existing utility
            $transaction = $transactionUtil->createSellTransaction(
                $sellData,
                $payload['sell_lines'] ?? [],
                $payload['payments'] ?? [],
                $user,
            );

            return ['server_id' => $transaction->id];
        });
    }

    private function processExpenseCreate($user, array $payload, string $localUuid): array
    {
        $existing = Transaction::where('ref_no', $localUuid)->where('type', 'expense')->first();
        if ($existing) {
            return ['server_id' => $existing->id, 'duplicate' => true];
        }

        return DB::transaction(function () use ($user, $payload, $localUuid) {
            $transactionUtil = app(\App\Utils\TransactionUtil::class);

            $expenseData = [
                'business_id' => $user->business_id,
                'location_id' => $payload['location_id'],
                'type' => 'expense',
                'status' => 'final',
                'payment_status' => 'paid',
                'contact_id' => $payload['contact_id'] ?? null,
                'expense_for' => $payload['expense_for'] ?? null,
                'expense_category_id' => $payload['expense_category_id'] ?? null,
                'transaction_date' => \Carbon::parse($payload['transaction_date'])->toDateTimeString(),
                'total_before_tax' => $payload['final_total'],
                'tax_id' => null,
                'tax_amount' => 0,
                'final_total' => $payload['final_total'],
                'additional_notes' => $payload['additional_notes'] ?? null,
                'created_by' => $user->id,
                'ref_no' => $localUuid,
            ];

            $transaction = Transaction::create($expenseData);

            if (!empty($payload['payment'])) {
                $transactionUtil->createOrUpdatePaymentLines($transaction, $payload['payment']);
                $transactionUtil->updatePaymentStatus($transaction->id, $transaction->final_total);
            }

            return ['server_id' => $transaction->id];
        });
    }

    private function processCashRegisterOpen($user, array $payload): array
    {
        $register = \App\CashRegister::where('id', $payload['register_id'])
                                             ->where('business_id', $user->business_id)
                                             ->firstOrFail();

        if ($register->status === 'open') {
            return ['server_id' => $register->id, 'already_open' => true];
        }

        $register->update([
            'status'         => 'open',
            'user_id'        => $user->id,
            'closing_amount' => $payload['opening_amount'],
        ]);

        \App\CashRegisterTransaction::create([
            'cash_register_id' => $register->id,
            'amount'           => $payload['opening_amount'],
            'pay_via'          => 'cash',
            'type'             => 'initial',
        ]);

        return ['server_id' => $register->id];
    }

    private function processCashRegisterClose($user, array $payload): array
    {
        $register = \App\CashRegister::where('id', $payload['register_id'])
                                             ->where('business_id', $user->business_id)
                                             ->firstOrFail();

        $register->update([
            'status'         => 'close',
            'closing_amount' => $payload['closing_amount'],
        ]);

        return ['server_id' => $register->id];
    }

    // ─── Phase 2: Inventory & Contacts ────────────────────────────────────────

    private function processStockAdjustment($user, array $payload, string $localUuid): array
    {
        // Idempotency
        $existing = Transaction::where('ref_no', $localUuid)->first();
        if ($existing) return ['server_id' => $existing->id, 'duplicate' => true];

        return DB::transaction(function () use ($user, $payload, $localUuid) {
            $type       = $payload['type'] ?? 'normal'; // normal | recovered | damaged | stock_transfer
            $locationId = $payload['location_id'] ?? ($payload['from_location_id'] ?? null);

            if ($type === 'stock_transfer') {
                // Use existing StockTransferUtil if available, otherwise manual
                $fromLocationId = $payload['from_location_id'];
                $toLocationId   = $payload['to_location_id'];

                $transaction = Transaction::create([
                    'business_id'      => $user->business_id,
                    'location_id'      => $fromLocationId,
                    'type'             => 'stock_transfer',
                    'status'           => 'completed',
                    'transaction_date' => $payload['transaction_date'],
                    'final_total'      => 0,
                    'ref_no'           => $localUuid,
                    'additional_notes' => $payload['note'] ?? null,
                ]);

                foreach ($payload['transfer_lines'] ?? [] as $line) {
                    // Deduct from source
                    \App\VariationLocationDetails::where('variation_id', $line['variation_id'])
                        ->where('location_id', $fromLocationId)
                        ->decrement('qty_available', $line['quantity']);

                    // Add to destination
                    \App\VariationLocationDetails::updateOrCreate(
                        ['variation_id' => $line['variation_id'], 'location_id' => $toLocationId],
                        ['qty_available' => DB::raw('qty_available + ' . (float)$line['quantity'])],
                    );
                }

                return ['server_id' => $transaction->id];
            }

            // Standard adjustment (write-off / recovered / damaged)
            $transaction = Transaction::create([
                'business_id'      => $user->business_id,
                'location_id'      => $locationId,
                'type'             => 'stock_adjustment',
                'status'           => 'received',
                'transaction_date' => $payload['transaction_date'],
                'final_total'      => 0,
                'ref_no'           => $localUuid,
                'additional_notes' => $payload['note'] ?? null,
            ]);

            $sign = ($type === 'recovered') ? 1 : -1;

            foreach ($payload['adjustment_lines'] ?? [] as $line) {
                $delta = abs($line['quantity']) * $sign;
                \App\VariationLocationDetails::where('variation_id', $line['variation_id'])
                    ->where('location_id', $locationId)
                    ->increment('qty_available', $delta);
            }

            return ['server_id' => $transaction->id];
        });
    }

    private function processPurchaseCreate($user, array $payload, string $localUuid): array
    {
        $existing = Transaction::where('ref_no', $localUuid)->first();
        if ($existing) return ['server_id' => $existing->id, 'duplicate' => true];

        return DB::transaction(function () use ($user, $payload, $localUuid) {
            /** @var \App\Utils\TransactionUtil $transactionUtil */
            $transactionUtil = app(\App\Utils\TransactionUtil::class);

            $purchaseData = [
                'business_id'      => $user->business_id,
                'location_id'      => $payload['location_id'],
                'contact_id'       => $payload['contact_id'] ?? null,
                'type'             => 'purchase',
                'status'           => 'received',
                'payment_status'   => $payload['payment_status'] ?? 'due',
                'transaction_date' => $payload['transaction_date'],
                'final_total'      => $payload['final_total'],
                'total_before_tax' => $payload['total_before_tax'],
                'tax_amount'       => $payload['tax_amount'],
                'ref_no'           => $payload['ref_no'] ?? $localUuid,
                'additional_notes' => $payload['note'] ?? null,
            ];

            $transaction = $transactionUtil->createPurchaseTransaction(
                $purchaseData,
                $payload['purchase_lines'] ?? [],
                $user,
            );

            return ['server_id' => $transaction->id];
        });
    }

    private function processContactCreate($user, array $payload, string $localUuid): array
    {
        // Idempotency via name + mobile match
        $existing = \App\Contact::where('business_id', $user->business_id)
            ->where('name', $payload['name'])
            ->when(!empty($payload['mobile']), fn($q) => $q->where('mobile', $payload['mobile']))
            ->first();

        if ($existing) return ['server_id' => $existing->id, 'duplicate' => true];

        $contact = \App\Contact::create([
            'business_id'  => $user->business_id,
            'type'         => $payload['type'],
            'name'         => $payload['name'],
            'mobile'       => $payload['mobile'] ?? null,
            'email'        => $payload['email'] ?? null,
            'credit_limit' => $payload['credit_limit'] ?? 0,
            'is_active'    => 1,
        ]);

        return ['server_id' => $contact->id];
    }

    private function processContactUpdate($user, array $payload): array
    {
        $contact = \App\Contact::where('business_id', $user->business_id)
            ->findOrFail($payload['server_id']);

        $contact->update(array_filter([
            'name'         => $payload['name'] ?? null,
            'mobile'       => $payload['mobile'] ?? null,
            'email'        => $payload['email'] ?? null,
            'credit_limit' => $payload['credit_limit'] ?? null,
        ], fn($v) => $v !== null));

        return ['server_id' => $contact->id];
    }

    private function processPurchaseReturnCreate($user, array $payload, string $localUuid): array
    {
        $existing = Transaction::where('business_id', $user->business_id)
            ->where('type', 'purchase_return')
            ->where('ref_no', $localUuid)
            ->first();

        if ($existing) return ['server_id' => $existing->id, 'duplicate' => true];

        return DB::transaction(function () use ($user, $payload, $localUuid) {
            $purchase = Transaction::where('business_id', $user->business_id)
                ->where('type', 'purchase')
                ->with(['purchase_lines', 'purchase_lines.sub_unit'])
                ->findOrFail($payload['transaction_id']);

            /** @var \App\Utils\ProductUtil $productUtil */
            $productUtil = app(\App\Utils\ProductUtil::class);
            /** @var \App\Utils\TransactionUtil $transactionUtil */
            $transactionUtil = app(\App\Utils\TransactionUtil::class);

            $return_quantities = $payload['returns'] ?? [];
            $return_total = 0;

            foreach ($purchase->purchase_lines as $purchase_line) {
                $old_return_qty = $purchase_line->quantity_returned;
                $return_quantity = !empty($return_quantities[$purchase_line->id]) ? $productUtil->num_uf($return_quantities[$purchase_line->id]) : 0;

                $multiplier = 1;
                if (!empty($purchase_line->sub_unit->base_unit_multiplier)) {
                    $multiplier = $purchase_line->sub_unit->base_unit_multiplier;
                    $return_quantity = $return_quantity * $multiplier;
                }

                $purchase_line->quantity_returned = $return_quantity;
                $purchase_line->save();
                $return_total += $purchase_line->purchase_price_inc_tax * $purchase_line->quantity_returned;

                // Decrease quantity in variation location details
                if ($old_return_qty != $purchase_line->quantity_returned) {
                    $productUtil->decreaseProductQuantity(
                        $purchase_line->product_id,
                        $purchase_line->variation_id,
                        $purchase->location_id,
                        $purchase_line->quantity_returned,
                        $old_return_qty
                    );
                }
            }

            $return_total_inc_tax = $return_total + ($payload['tax_amount'] ?? 0);

            $return_transaction_data = [
                'business_id' => $user->business_id,
                'location_id' => $purchase->location_id,
                'type' => 'purchase_return',
                'status' => 'final',
                'contact_id' => $purchase->contact_id,
                'transaction_date' => \Carbon::now(),
                'created_by' => $user->id,
                'return_parent_id' => $purchase->id,
                'total_before_tax' => $return_total,
                'final_total' => $return_total_inc_tax,
                'tax_amount' => $payload['tax_amount'] ?? 0,
                'tax_id' => $purchase->tax_id,
                'ref_no' => $localUuid,
            ];

            $return_transaction = Transaction::where('business_id', $user->business_id)
                ->where('type', 'purchase_return')
                ->where('return_parent_id', $purchase->id)
                ->first();

            if (!empty($return_transaction)) {
                $return_transaction->update($return_transaction_data);
            } else {
                $return_transaction = Transaction::create($return_transaction_data);
            }

            // Update payment status
            $transactionUtil->updatePaymentStatus($return_transaction->id, $return_transaction->final_total);

            return ['server_id' => $return_transaction->id];
        });
    }

    private function processProductCreate($user, array $payload, string $localUuid): array
    {
        $existing = \App\Product::where('business_id', $user->business_id)
            ->where('sku', $payload['sku'])
            ->first();

        if ($existing) return ['server_id' => $existing->id, 'duplicate' => true];

        return DB::transaction(function () use ($user, $payload, $localUuid) {
            $product = \App\Product::create([
                'business_id' => $user->business_id,
                'name' => $payload['name'],
                'type' => 'single',
                'sku' => $payload['sku'],
                'category_id' => $payload['category_id'] ?? null,
                'warranty_id' => $payload['warranty_id'] ?? null,
                'enable_sr_no' => $payload['enable_sr_no'] ?? 0,
                'unit_id' => $payload['unit_id'] ?? \App\Unit::where('business_id', $user->business_id)->first()->id,
                'brand_id' => $payload['brand_id'] ?? null,
                'tax' => $payload['tax_id'] ?? null,
                'tax_type' => 'exclusive',
                'barcode_type' => 'C128',
                'enable_stock' => 1,
                'alert_quantity' => $payload['alert_quantity'] ?? 0,
                'created_by' => $user->id,
            ]);

            \App\Variation::create([
                'product_id' => $product->id,
                'name' => 'DUMMY',
                'sub_sku' => $product->sku,
                'default_purchase_price' => $payload['purchase_price'] ?? 0,
                'dpp_inc_tax' => $payload['purchase_price'] ?? 0,
                'profit_percent' => 0,
                'default_sell_price' => $payload['sell_price'] ?? 0,
                'sell_price_inc_tax' => $payload['sell_price'] ?? 0,
            ]);

            return ['server_id' => $product->id];
        });
    }
}
