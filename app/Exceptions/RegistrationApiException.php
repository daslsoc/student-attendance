<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the student-registration integration can't be used — not
 * configured, unreachable, or it rejected our token. Carries a message safe to
 * show a logged-in teacher.
 */
class RegistrationApiException extends RuntimeException {}
