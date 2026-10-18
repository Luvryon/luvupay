<?php

namespace App\Exceptions;

use RuntimeException;

/** Raised when a journal is unbalanced/invalid or ledger rows are mutated. Always fail closed. */
class LedgerException extends RuntimeException {}
