<?php

namespace App\Exceptions;

use RuntimeException;

/** A coupon cannot be used. The message is written for the student and is shown as it is. */
class CouponException extends RuntimeException {}
