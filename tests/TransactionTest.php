<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PaigeJulianne\PicoORM;

/**
 * Tests for PicoORM transaction support
 */
class TransactionTest extends TestCase
{
    protected function setUp(): void
    {
        TestDatabaseHelper::setupFileDatabase();
        PicoORM::clearConnectionCache();
    }

    protected function tearDown(): void
    {
        // Ensure no lingering transactions
        if (PicoORM::inTransaction()) {
            PicoORM::rollback();
        }
        PicoORM::clearConnectionCache();
        TestDatabaseHelper::cleanup();
    }

    // =========================================================================
    // Basic Transaction Tests
    // =========================================================================

    public function testBeginTransaction(): void
    {
        $this->assertFalse(PicoORM::inTransaction());

        PicoORM::beginTransaction();

        $this->assertTrue(PicoORM::inTransaction());

        PicoORM::rollback();
    }

    public function testCommitTransaction(): void
    {
        PicoORM::beginTransaction();

        $model = new TestModel();
        $model->name = 'Transaction Test';
        $model->save();
        $id = $model->getId();

        PicoORM::commit();

        $this->assertFalse(PicoORM::inTransaction());

        // Verify record exists after commit
        $this->assertTrue(TestModel::exists($id));
    }

    public function testRollbackTransaction(): void
    {
        // Create a record first to have something to work with
        $existingModel = new TestModel();
        $existingModel->name = 'Existing';
        $existingModel->save();

        $countBefore = TestModel::count();

        PicoORM::beginTransaction();

        $model = new TestModel();
        $model->name = 'Will Be Rolled Back';
        $model->save();

        PicoORM::rollback();

        $this->assertFalse(PicoORM::inTransaction());

        // Count should be same as before
        $countAfter = TestModel::count();
        $this->assertEquals($countBefore, $countAfter);
    }

    public function testInTransactionReturnsFalseWhenNotInTransaction(): void
    {
        $this->assertFalse(PicoORM::inTransaction());
    }

    // =========================================================================
    // transaction() Callback Tests
    // =========================================================================

    public function testTransactionCallbackCommitsOnSuccess(): void
    {
        $result = PicoORM::transaction(function () {
            $model = new TestModel();
            $model->name = 'Callback Test';
            $model->save();

            return $model->getId();
        });

        $this->assertNotNull($result);
        $this->assertTrue(TestModel::exists($result));
    }

    public function testTransactionCallbackRollsBackOnException(): void
    {
        $countBefore = TestModel::count();

        try {
            PicoORM::transaction(function () {
                $model = new TestModel();
                $model->name = 'Will Fail';
                $model->save();

                throw new \RuntimeException('Intentional failure');
            });
        } catch (\RuntimeException $e) {
            $this->assertEquals('Intentional failure', $e->getMessage());
        }

        $countAfter = TestModel::count();
        $this->assertEquals($countBefore, $countAfter);
    }

    public function testTransactionCallbackReturnsValue(): void
    {
        $result = PicoORM::transaction(function () {
            return 'test value';
        });

        $this->assertEquals('test value', $result);
    }

    public function testTransactionCallbackRethrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Test exception');

        PicoORM::transaction(function () {
            throw new \InvalidArgumentException('Test exception');
        });
    }

    // =========================================================================
    // Nested/Multiple Operation Tests
    // =========================================================================

    public function testMultipleOperationsInTransaction(): void
    {
        PicoORM::transaction(function () {
            $model1 = new TestModel();
            $model1->name = 'First';
            $model1->save();

            $model2 = new TestModel();
            $model2->name = 'Second';
            $model2->save();

            $model3 = new TestModel();
            $model3->name = 'Third';
            $model3->save();
        });

        $this->assertEquals(3, TestModel::count());
    }

    public function testUpdateInTransaction(): void
    {
        // Create initial record
        $model = new TestModel();
        $model->name = 'Original';
        $model->save();
        $id = $model->getId();

        PicoORM::transaction(function () use ($id) {
            $model = new TestModel($id);
            $model->name = 'Updated';
            $model->save();
        });

        $loaded = new TestModel($id);
        $this->assertEquals('Updated', $loaded->name);
    }

    public function testDeleteInTransaction(): void
    {
        // Create initial record
        $model = new TestModel();
        $model->name = 'To Delete';
        $model->save();
        $id = $model->getId();

        PicoORM::transaction(function () use ($id) {
            $model = new TestModel($id);
            $model->delete();
        });

        $this->assertFalse(TestModel::exists($id));
    }

    // =========================================================================
    // Connection Cache Tests
    // =========================================================================

    public function testClearConnectionCache(): void
    {
        PicoORM::beginTransaction();
        $this->assertTrue(PicoORM::inTransaction());

        PicoORM::rollback();
        PicoORM::clearConnectionCache();

        // After clearing cache, inTransaction should return false
        // because there's no cached connection
        $this->assertFalse(PicoORM::inTransaction());
    }

    public function testClearSpecificConnectionCache(): void
    {
        // Start transaction on default connection
        PicoORM::beginTransaction('default');
        $this->assertTrue(PicoORM::inTransaction('default'));

        PicoORM::rollback('default');
        PicoORM::clearConnectionCache('default');

        $this->assertFalse(PicoORM::inTransaction('default'));
    }
}
