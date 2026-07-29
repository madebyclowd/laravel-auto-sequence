<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use MadeByClowd\AutoSequence\Exceptions\AutoSequenceException;
use MadeByClowd\AutoSequence\Facades\Sequence;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestMaxInvoice;
use MadeByClowd\AutoSequence\Tests\Feature\Fixtures\TestNoManualInvoice;

class ValidationTest extends SequencingTestCase
{
    /** @test */
    public function test_it_enforces_max_value_limit()
    {
        TestMaxInvoice::create(); // mx-1
        TestMaxInvoice::create(); // mx-2
        TestMaxInvoice::create(); // mx-3

        $this->expectException(AutoSequenceException::class);
        $this->expectExceptionMessage('Sequence [adv_max][MX] has exceeded its maximum limit of 3');

        TestMaxInvoice::create(); // MX-4 should trigger exception
    }

    /** @test */
    public function test_it_enforces_manual_override_block()
    {
        $this->expectException(AutoSequenceException::class);
        $this->expectExceptionMessage("Manual assignment of sequence number on field 'seq_no_manual' is not allowed.");

        TestNoManualInvoice::create(['seq_no_manual' => 'MANUAL-123']);
    }

    /** @test */
    public function test_it_validates_pad_length()
    {
        $this->expectException(AutoSequenceException::class);
        $this->expectExceptionMessage("Sequence config 'pad_length' must be a positive integer greater than 0.");

        Sequence::generate('order', 'SO', '202606', '{seq}', 0);
    }

    /** @test */
    public function test_it_validates_start_value()
    {
        $this->expectException(AutoSequenceException::class);
        $this->expectExceptionMessage("Sequence config 'start_value' must be greater than or equal to 0.");

        Sequence::generate('order', 'SO', '202606', '{seq}', 5, 'default', null, null, -1);
    }

    /** @test */
    public function test_it_validates_step()
    {
        $this->expectException(AutoSequenceException::class);
        $this->expectExceptionMessage("Sequence config 'step' must be a positive integer greater than 0.");

        Sequence::generate('order', 'SO', '202606', '{seq}', 5, 'default', null, null, 1, 0);
    }
}
