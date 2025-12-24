<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

/**
 * Tests for PicoORM atomic operations (increment, decrement)
 */
class AtomicOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabaseHelper::setupFileDatabase();
    }

    protected function tearDown(): void
    {
        TestDatabaseHelper::cleanup();
    }

    private function createTestRecord(): TestModel
    {
        $model = new TestModel();
        $model->setMulti([
            'name' => 'Test Record',
            'email' => 'test@example.com',
            'view_count' => 10,
            'price' => 99.99
        ]);
        $model->save();

        return new TestModel($model->getId());
    }

    // =========================================================================
    // increment() Tests
    // =========================================================================

    public function testIncrementByDefault(): void
    {
        $model = $this->createTestRecord();
        $originalCount = (int)$model->view_count;

        $model->increment('view_count');

        $this->assertEquals($originalCount + 1, (int)$model->view_count);

        // Verify in database
        $fresh = new TestModel($model->getId());
        $this->assertEquals($originalCount + 1, (int)$fresh->view_count);
    }

    public function testIncrementBySpecificAmount(): void
    {
        $model = $this->createTestRecord();
        $originalCount = (int)$model->view_count;

        $model->increment('view_count', 5);

        $this->assertEquals($originalCount + 5, (int)$model->view_count);
    }

    public function testIncrementFloatColumn(): void
    {
        $model = $this->createTestRecord();
        $originalPrice = (float)$model->price;

        $model->increment('price', 0.50);

        $this->assertEquals($originalPrice + 0.50, (float)$model->price, '', 0.001);
    }

    public function testIncrementThrowsOnUnsavedRecord(): void
    {
        $model = new TestModel();
        $model->name = 'Unsaved';
        $model->view_count = 0;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot increment column on unsaved record');

        $model->increment('view_count');
    }

    public function testIncrementThrowsOnInvalidColumn(): void
    {
        $model = $this->createTestRecord();

        $this->expectException(\InvalidArgumentException::class);

        $model->increment('invalid-column');
    }

    // =========================================================================
    // decrement() Tests
    // =========================================================================

    public function testDecrementByDefault(): void
    {
        $model = $this->createTestRecord();
        $originalCount = (int)$model->view_count;

        $model->decrement('view_count');

        $this->assertEquals($originalCount - 1, (int)$model->view_count);

        // Verify in database
        $fresh = new TestModel($model->getId());
        $this->assertEquals($originalCount - 1, (int)$fresh->view_count);
    }

    public function testDecrementBySpecificAmount(): void
    {
        $model = $this->createTestRecord();
        $originalCount = (int)$model->view_count;

        $model->decrement('view_count', 3);

        $this->assertEquals($originalCount - 3, (int)$model->view_count);
    }

    public function testDecrementFloatColumn(): void
    {
        $model = $this->createTestRecord();
        $originalPrice = (float)$model->price;

        $model->decrement('price', 10.00);

        $this->assertEquals($originalPrice - 10.00, (float)$model->price, '', 0.001);
    }

    public function testDecrementThrowsOnUnsavedRecord(): void
    {
        $model = new TestModel();
        $model->name = 'Unsaved';

        $this->expectException(\RuntimeException::class);

        $model->decrement('view_count');
    }

    // =========================================================================
    // Atomic Behavior Tests
    // =========================================================================

    public function testIncrementIsAtomic(): void
    {
        $model = $this->createTestRecord();
        $id = $model->getId();

        // Simulate another process updating the value
        TestModel::_doQuery(
            'UPDATE test_table SET view_count = 100 WHERE id = ?',
            [$id]
        );

        // Our model still has old value in memory
        $this->assertEquals(10, (int)$model->view_count);

        // Increment should use database value, not in-memory value
        $model->increment('view_count', 1);

        // Should be 101, not 11
        $this->assertEquals(101, (int)$model->view_count);
    }

    public function testMultipleIncrements(): void
    {
        $model = $this->createTestRecord();

        $model->increment('view_count', 1);
        $model->increment('view_count', 2);
        $model->increment('view_count', 3);

        $this->assertEquals(16, (int)$model->view_count); // 10 + 1 + 2 + 3
    }

    public function testIncrementAndDecrement(): void
    {
        $model = $this->createTestRecord();

        $model->increment('view_count', 10);
        $model->decrement('view_count', 5);

        $this->assertEquals(15, (int)$model->view_count); // 10 + 10 - 5
    }
}
