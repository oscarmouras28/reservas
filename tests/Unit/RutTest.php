<?php

namespace Tests\Unit;

use App\Support\Rut;
use PHPUnit\Framework\TestCase;

class RutTest extends TestCase
{
    public function test_acepta_y_normaliza_ruts_validos(): void
    {
        $this->assertSame('12345678-5', Rut::normalize('12.345.678-5'));
        $this->assertSame('12345678-5', Rut::normalize('123456785'));
        $this->assertSame('11111111-1', Rut::normalize(' 11.111.111-1 '));
    }

    public function test_acepta_digito_k(): void
    {
        $this->assertSame('K', Rut::checkDigit('1000005'));
        $this->assertSame('1000005-K', Rut::normalize('1.000.005-k'));
    }

    public function test_rechaza_ruts_invalidos(): void
    {
        $this->assertNull(Rut::normalize('12.345.678-9'));
        $this->assertNull(Rut::normalize('abc'));
        $this->assertNull(Rut::normalize(''));
        $this->assertNull(Rut::normalize('000000-0'));
        $this->assertNull(Rut::normalize('1234567890-1'));
    }
}
