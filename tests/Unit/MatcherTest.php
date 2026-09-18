<?php

namespace Tests\Unit;

use App\Services\Matcher;
use PHPUnit\Framework\TestCase;

class MatcherTest extends TestCase
{
    public function test_valid_gtins_and_check_digit(): void
    {
        $m = new Matcher;
        $this->assertSame('04006381333931', $m->gtin('4006381333931'));
        $this->assertNull($m->gtin('5941234567890'));
        $this->assertSame('exact', $m->match('4006381333931', ['title' => 'X', 'ean' => '04006381333931'])['kind']);
        $this->assertSame('none', $m->match('4006381333931', ['title' => 'X'])['kind']);
    }

    public function test_text_is_never_declared_identical(): void
    {
        $m = new Matcher;
        $this->assertSame('telefon samsung', $m->normalize('  TELEFON, Samsung! '));
        $this->assertSame('similar', $m->match('Samsung S24', ['title' => 'Samsung S24 Ultra'])['kind']);
        $this->assertSame('none', $m->match('Samsung S24', ['title' => 'Apple iPhone'])['kind']);
    }
}
