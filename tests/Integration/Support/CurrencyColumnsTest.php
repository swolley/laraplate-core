<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Inspector\SchemaInspector;
use Modules\Core\Support\CurrencyColumns;
use Modules\Core\Tests\Stubs\Crud\AggregateCustomerStubModel;
use Modules\Core\Tests\Stubs\Crud\AggregateOrderStubModel;
use Modules\Core\Tests\Support\AggregateTestKit;

beforeEach(function (): void {
    AggregateTestKit::createTables();
});

it('finds currency beside total', function (): void {
    expect(CurrencyColumns::for(new AggregateOrderStubModel, 'total'))->toBe('currency');
});

it('pairs amount_doc with currency_doc and amount_local with currency_local', function (): void {
    Schema::table('aggregate_orders', function (Blueprint $table): void {
        $table->decimal('amount_doc', 15, 4)->nullable();
        $table->decimal('amount_local', 15, 4)->nullable();
        $table->char('currency_doc', 3)->nullable();
        $table->char('currency_local', 3)->nullable();
    });
    SchemaInspector::getInstance()->clearTable('aggregate_orders');

    $order = new AggregateOrderStubModel;

    expect(CurrencyColumns::for($order, 'amount_doc'))->toBe('currency_doc')
        ->and(CurrencyColumns::for($order, 'amount_local'))->toBe('currency_local')
        ->and(CurrencyColumns::for($order, 'total'))->toBe('currency');
});

it('falls back to currency when the paired column does not exist', function (): void {
    Schema::table('aggregate_orders', function (Blueprint $table): void {
        $table->decimal('amount_doc', 15, 4)->nullable();
    });
    SchemaInspector::getInstance()->clearTable('aggregate_orders');

    expect(CurrencyColumns::for(new AggregateOrderStubModel, 'amount_doc'))->toBe('currency');
});

it('pairs a field with no underscore by its whole name', function (): void {
    Schema::table('aggregate_orders', function (Blueprint $table): void {
        $table->char('currency_total', 3)->nullable();
    });
    SchemaInspector::getInstance()->clearTable('aggregate_orders');

    expect(CurrencyColumns::for(new AggregateOrderStubModel, 'total'))->toBe('currency_total');
});

it('returns null with no currency column', function (): void {
    expect(CurrencyColumns::for(new AggregateCustomerStubModel, 'id'))->toBeNull()
        ->and(CurrencyColumns::for(new AggregateCustomerStubModel, 'credit_limit'))->toBeNull();
});
