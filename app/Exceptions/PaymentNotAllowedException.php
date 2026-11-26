<?php

namespace App\Exceptions;

use RuntimeException;

/** No se puede iniciar el pago. El mensaje se muestra al paciente. */
class PaymentNotAllowedException extends RuntimeException {}
