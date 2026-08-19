<?php

declare(strict_types=1);

/**
 * Souvera Central - Exception für fehlende Souvera-Admin-Berechtigung.
 *
 * Wird von der SouveraAdminMiddleware geworfen, wenn ein Benutzer eine
 * Central-Route aufruft, ohne Souvera-Administrator (NC-Superadmin oder
 * scadmin-Mitglied) zu sein.
 */

namespace OCA\SouveraCentral\Exception;

class NotSouveraAdminException extends \Exception {
    public function __construct(string $message = 'Souvera-Administrator-Berechtigung erforderlich.') {
        parent::__construct($message);
    }
}
