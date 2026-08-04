<?php

namespace MadeByClowd\AutoSequence\Tests\Feature\Sequencing;

use MadeByClowd\AutoSequence\Facades\Sequence;

class ChecksumTest extends SequencingTestCase
{
    /** @test */
    public function test_checksum_token_produces_a_stable_correct_luhn_digit()
    {
        $formatted = Sequence::generate(
            'checksum_test',
            'INV',
            '2026',
            'INV-{YYYY}-{seq:5}-{checksum:mod10}',
            5
        );

        $this->assertEquals('INV-2026-00001-4', $formatted);
    }

    /** @test */
    public function test_is_valid_checksum_returns_true_for_a_freshly_generated_number()
    {
        $formatted = Sequence::generate(
            'checksum_valid',
            'INV',
            '2026',
            'INV-{YYYY}-{seq:5}-{checksum:mod10}',
            5
        );

        $this->assertTrue(Sequence::isValidChecksum($formatted));
    }

    /** @test */
    public function test_is_valid_checksum_returns_false_after_flipping_one_digit()
    {
        $formatted = Sequence::generate(
            'checksum_flip',
            'INV',
            '2026',
            'INV-{YYYY}-{seq:5}-{checksum:mod10}',
            5
        );

        $mutated = substr_replace($formatted, '9', strrpos($formatted, '1'), 1);

        $this->assertNotEquals($formatted, $mutated);
        $this->assertFalse(Sequence::isValidChecksum($mutated));
    }

    /** @test */
    public function test_is_valid_checksum_returns_false_after_transposing_adjacent_digits()
    {
        $formatted = Sequence::generate(
            'checksum_transpose',
            'INV',
            '2026',
            'INV-{YYYY}-{seq:5}-{checksum:mod10}',
            5
        );

        // Swap the first two digits of the year (2-0 -> 0-2), an adjacent
        // transposition Luhn is guaranteed to catch since |2-0| !== 9.
        $mutated = str_replace('INV-20', 'INV-02', $formatted);

        $this->assertNotEquals($formatted, $mutated);
        $this->assertFalse(Sequence::isValidChecksum($mutated));
    }

    /** @test */
    public function test_checksum_token_combines_correctly_with_a_preceding_rand_token()
    {
        $formatted = Sequence::generate(
            'checksum_rand',
            'INV',
            '2026',
            'INV-{rand:6}-{seq:5}-{checksum:mod10}',
            5
        );

        $this->assertTrue(Sequence::isValidChecksum($formatted));
    }
}
