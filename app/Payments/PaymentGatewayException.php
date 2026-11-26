<?php

namespace App\Payments;

use RuntimeException;

/** La pasarela no respondió o respondió con error. */
class PaymentGatewayException extends RuntimeException {}
