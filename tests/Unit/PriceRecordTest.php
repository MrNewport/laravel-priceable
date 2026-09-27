<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use MrNewport\LaravelPriceable\Exceptions\PriceNotFoundException;
use MrNewport\LaravelPriceable\Facades\PriceableFacade;
use MrNewport\LaravelPriceable\Models\Price;
use MrNewport\LaravelPriceable\Models\PriceList;
use MrNewport\LaravelPriceable\Services\PriceCalculator;
use MrNewport\LaravelPriceable\Tests\Models\CurrencyPricedProductTestModel;
use MrNewport\LaravelPriceable\Tests\Models\ExactPricedProductTestModel;
use MrNewport\LaravelPriceable\Tests\Models\ExactPriceTestModel;
use MrNewport\LaravelPriceable\Tests\Models\UserTestModel;

beforeEach(function () {
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('email')->nullable();
        $table->timestamps();
    });
    Schema::create('exact_products', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('workspace_id');
        $table->timestamps();
    });
    Schema::create('exact_prices', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('workspace_id');
        $table->morphs('priceable');
        $table->unsignedBigInteger('price_list_id')->nullable();
        $table->unsignedInteger('min_quantity')->default(1);
        $table->unsignedInteger('max_quantity')->nullable();
        $table->string('unit_price');
        $table->string('currency')->default('USD');
        $table->dateTime('valid_from')->nullable();
        $table->dateTime('valid_to')->nullable();
        $table->timestamps();
    });
    Schema::create('exact_price_scopes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('price_id')->constrained('exact_prices');
        $table->string('scope_type');
        $table->string('scope_value');
        $table->timestamps();
    });
});

test('record resolution preserves exact saved strings and caller relation ownership without writes', function () {
    $product = ExactPricedProductTestModel::create(['workspace_id' => 7]);
    $good = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '9007199254740993.0001']);
    $product->prices()->create(['workspace_id' => 8, 'unit_price' => '1', 'min_quantity' => 5]);
    $before = DB::table('exact_prices')->orderBy('id')->get()->toArray();

    $found = $product->priceRecordFor(5);

    expect($found)->toBeInstanceOf(ExactPriceTestModel::class)
        ->and($found->id)->toBe($good->id)
        ->and($found->unit_price)->toBe('9007199254740993.0001')
        ->and($found->getRawOriginal('unit_price'))->toBe('9007199254740993.0001')
        ->and(DB::table('exact_prices')->orderBy('id')->get()->toArray())->toEqual($before);
});

test('an explicit effective clock selects future records with inclusive existing validity boundaries', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-01 00:00:00', 'UTC'));
    try {
        $product = ExactPricedProductTestModel::create(['workspace_id' => 7]);
        $current = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '99', 'valid_to' => '2026-01-01 23:59:59']);
        $future = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '0', 'valid_from' => '2026-07-01 00:00:00', 'valid_to' => '2026-07-02 23:59:59']);
        foreach (['2026-07-01 00:00:00', '2026-07-02 23:59:59'] as $boundary) {
            $clock = new DateTimeImmutable($boundary, new DateTimeZone('UTC'));
            expect($product->priceRecordFor(effectiveAt: $clock)->id)->toBe($future->id)
                ->and($product->priceRecordFor(effectiveAt: $clock)->unit_price)->toBe('0')
                ->and($clock->format('Y-m-d H:i:s'))->toBe($boundary);
        }
        expect($product->priceRecordFor()->id)->toBe($current->id)
            ->and($product->priceFor())->toBe(99.0)
            ->and($product->priceRecordFor(effectiveAt: CarbonImmutable::parse('2026-06-30 23:59:59', 'UTC')))->toBeNull()
            ->and($product->priceRecordFor(effectiveAt: CarbonImmutable::parse('2026-07-03 00:00:00', 'UTC')))->toBeNull();
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('record resolution retains user list priority tiers and exact separate scope dimensions', function () {
    $product = ExactPricedProductTestModel::create(['workspace_id' => 7]);
    $user = UserTestModel::create();
    $list = PriceList::create(['name' => 'Contract']);
    $list->users()->attach($user->id);
    $default = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '10']);
    $contract = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '8', 'price_list_id' => $list->id, 'min_quantity' => 5, 'max_quantity' => 10]);
    foreach ([$default, $contract] as $row) {
        $row->scopes()->createMany([['scope_type' => 'currency', 'scope_value' => 'USD'], ['scope_type' => 'room', 'scope_value' => '101']]);
    }
    $clock = CarbonImmutable::parse('2026-07-01', 'UTC');
    expect($product->priceRecordFor(5, $user, ['room' => '101', 'currency' => 'USD'], $clock)->id)->toBe($contract->id)
        ->and($product->priceRecordFor(11, $user, ['room' => '101', 'currency' => 'USD'], $clock)->id)->toBe($default->id)
        ->and($product->priceRecordFor(5, $user, ['room' => '101'], $clock))->toBeNull()
        ->and($product->priceRecordFor(5, $user, ['room' => '101', 'currency' => 'EUR'], $clock))->toBeNull();
});

test('record resolution retains configured missing-price failure policy', function () {
    config(['priceable.throw_exception_if_no_price_found' => true]);
    ExactPricedProductTestModel::create(['workspace_id' => 7])->priceRecordFor(effectiveAt: CarbonImmutable::parse('2026-07-01', 'UTC'));
})->throws(PriceNotFoundException::class);

test('legacy protected findPrice overrides remain usable without signature changes', function () {
    $calculator = new class extends PriceCalculator
    {
        protected function findPrice(Model $priceableModel, int $quantity, ?int $priceListId, array $scopes): ?Price
        {
            return new Price(['unit_price' => '7.2500']);
        }
    };
    $product = new ExactPricedProductTestModel(['workspace_id' => 7]);
    expect($calculator->resolvePrice($product)->unit_price)->toBe('7.2500')
        ->and($calculator->getPrice($product))->toBe(7.25);
});

test('record service facade and trait do not evaluate the amount accessor', function () {
    $record = new class extends Price
    {
        public function getUnitPriceAttribute($value)
        {
            throw new LogicException('Amount accessor evaluated.');
        }
    };
    $record->setRawAttributes(['unit_price' => '9007199254740993.0001'], true);
    $calculator = new class($record) extends PriceCalculator
    {
        public function __construct(private Price $record) {}

        protected function findPrice(Model $priceableModel, int $quantity, ?int $priceListId, array $scopes): ?Price
        {
            return $this->record;
        }
    };
    app()->instance('priceable.calculator', $calculator);
    $product = new ExactPricedProductTestModel(['workspace_id' => 7]);

    expect($calculator->resolvePrice($product))->toBe($record)
        ->and(PriceableFacade::resolvePrice($product))->toBe($record)
        ->and($product->priceRecordFor())->toBe($record)
        ->and($record->getRawOriginal('unit_price'))->toBe('9007199254740993.0001');
    expect(fn () => $product->priceFor())->toThrow(LogicException::class, 'Amount accessor evaluated.');
});

test('loaded list order precedes amount and tier preference with inclusive quantity bounds', function () {
    $product = ExactPricedProductTestModel::create(['workspace_id' => 7]);
    $user = UserTestModel::create();
    $first = PriceList::create(['name' => 'First']);
    $second = PriceList::create(['name' => 'Second']);
    $user->priceLists()->attach([$first->id, $second->id]);
    $user->setRelation('priceLists', $user->priceLists()->orderBy('id')->get());
    $base = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '20.00', 'price_list_id' => $first->id]);
    $tier = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '15.00', 'price_list_id' => $first->id, 'min_quantity' => 5, 'max_quantity' => 10]);
    $cheaper = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '1.00', 'price_list_id' => $second->id, 'min_quantity' => 5]);
    $clock = new DateTimeImmutable('2026-07-01 00:00:00', new DateTimeZone('UTC'));

    foreach ([4 => $base, 5 => $tier, 10 => $tier, 11 => $base] as $quantity => $expected) {
        expect($product->priceRecordFor($quantity, $user, effectiveAt: $clock)->id)->toBe($expected->id)
            ->and($product->priceFor($quantity, $user))->toBe((float) $expected->unit_price);
    }
    $user->setRelation('priceLists', $user->priceLists->reverse()->values());
    expect($product->priceRecordFor(5, $user, effectiveAt: $clock)->id)->toBe($cheaper->id);
});

test('both record clock paths retain every missing-price policy and valid default zero', function (bool $fallback, bool $throws) {
    config(['priceable.fallback_to_default_price' => $fallback, 'priceable.throw_exception_if_no_price_found' => $throws]);
    $product = ExactPricedProductTestModel::create(['workspace_id' => 7]);
    $user = UserTestModel::create();
    $list = PriceList::create(['name' => 'No matching price']);
    $user->priceLists()->attach($list->id);
    $clock = new DateTimeImmutable('2026-07-01 00:00:00', new DateTimeZone('UTC'));

    foreach ([fn () => $product->priceRecordFor(1, $user), fn () => $product->priceRecordFor(1, $user, effectiveAt: $clock), fn () => $product->priceFor(1, $user)] as $resolve) {
        if (! $fallback || $throws) {
            expect($resolve)->toThrow(PriceNotFoundException::class);
        } else {
            expect($resolve())->toBeNull();
        }
    }

    $zero = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '0.0000']);
    expect($product->priceRecordFor(1, $user)->id)->toBe($zero->id)
        ->and($product->priceRecordFor(1, $user, effectiveAt: $clock)->getRawOriginal('unit_price'))->toBe('0.0000')
        ->and($product->priceFor(1, $user))->toBe(0.0);
})->with([[true, false], [true, true], [false, false], [false, true]]);

test('explicit-clock hooks retain relation ownership and do not invoke the legacy hook', function () {
    $product = ExactPricedProductTestModel::create(['workspace_id' => 7]);
    $owned = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '8.0100', 'valid_from' => '2026-07-01 00:00:00']);
    $product->prices()->create(['workspace_id' => 8, 'unit_price' => '1', 'min_quantity' => 5]);
    $calculator = new class extends PriceCalculator
    {
        public ?DateTimeInterface $observedAt = null;

        protected function findPrice(Model $priceableModel, int $quantity, ?int $priceListId, array $scopes): ?Price
        {
            return new Price(['unit_price' => '7.25']);
        }

        protected function findPriceAt(Model $priceableModel, int $quantity, ?int $priceListId, array $scopes, DateTimeInterface $effectiveAt): ?Price
        {
            $this->observedAt = $effectiveAt;

            return parent::findPriceAt($priceableModel, $quantity, $priceListId, $scopes, $effectiveAt);
        }
    };
    $clock = new DateTime('2026-07-01 00:00:00', new DateTimeZone('UTC'));
    $before = clone $clock;

    expect($calculator->resolvePrice($product, 5, effectiveAt: $clock)->id)->toBe($owned->id)
        ->and($calculator->observedAt)->toBe($clock)
        ->and($clock)->toEqual($before)
        ->and($calculator->getPrice($product))->toBe(7.25);
});

test('host currency restrictions remain effective and default currency does not select a column', function () {
    config(['priceable.default_currency' => 'EUR']);
    $product = CurrencyPricedProductTestModel::create(['workspace_id' => 7]);
    $usd = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '8.2500', 'currency' => 'USD']);
    $product->prices()->create(['workspace_id' => 7, 'unit_price' => '1', 'currency' => 'EUR', 'min_quantity' => 5]);
    $clock = new DateTimeImmutable('2026-07-01 00:00:00', new DateTimeZone('UTC'));

    expect($product->priceRecordFor(5, effectiveAt: $clock)->id)->toBe($usd->id)
        ->and($product->priceRecordFor(5, effectiveAt: $clock)->currency)->toBe('USD')
        ->and($product->priceFor(5))->toBe(8.25);

    $unrestricted = ExactPricedProductTestModel::create(['workspace_id' => 7]);
    $row = $unrestricted->prices()->create(['workspace_id' => 7, 'unit_price' => '10.50', 'currency' => 'USD']);
    $row->scopes()->create(['scope_type' => 'currency', 'scope_value' => 'EUR']);
    expect($unrestricted->priceRecordFor(scopes: ['currency' => 'EUR'], effectiveAt: $clock)->currency)->toBe('USD');
});

test('record selection never broadens complete scopes to partial empty or duplicated dimensions', function () {
    $product = ExactPricedProductTestModel::create(['workspace_id' => 7]);
    $clock = new DateTimeImmutable('2026-07-01 00:00:00', new DateTimeZone('UTC'));
    $good = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '8.25']);
    $good->scopes()->createMany([['scope_type' => 'room', 'scope_value' => '101'], ['scope_type' => 'currency', 'scope_value' => 'USD']]);
    $duplicate = $product->prices()->create(['workspace_id' => 7, 'unit_price' => '1', 'min_quantity' => 5]);
    $duplicate->scopes()->createMany([['scope_type' => 'room', 'scope_value' => '101'], ['scope_type' => 'room', 'scope_value' => '101']]);

    expect($product->priceRecordFor(5, scopes: ['room' => '101', 'currency' => 'USD'], effectiveAt: $clock)->id)->toBe($good->id);
    foreach ([[], ['room' => '101'], ['room' => '101', 'currency' => 'USD', 'channel' => 'direct']] as $scopes) {
        expect($product->priceRecordFor(5, scopes: $scopes, effectiveAt: $clock))->toBeNull();
    }
});
