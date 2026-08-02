<?php

declare(strict_types=1);

namespace Seviye\Students\Support;

/**
 * One parsed CSV line from a toplu (bulk) student import - see
 * {@see StudentImportParser}. `error` is set when the row is structurally
 * malformed (wrong column count, a required field left empty); when it is
 * set, every other field is meaningless and StudentsRestController::import()
 * reports the error without attempting to create a student. This mirrors
 * StudentsRestController::store()'s own `InvalidArgumentException` catch for
 * business-rule errors (invalid education year, TC No) - THOSE are only
 * discovered later, when the row is actually handed to
 * StudentRepositoryInterface::create(), since validating a value object
 * requires the value object itself (kept out of this pure parser on purpose).
 */
final class StudentImportRow
{
    public function __construct(
        public readonly int $lineNumber,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $educationYear,
        public readonly string $className,
        public readonly ?string $tcNo,
        public readonly ?string $error
    ) {
    }
}
