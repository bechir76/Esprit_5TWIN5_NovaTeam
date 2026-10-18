<?php

namespace App\Exceptions;

use RuntimeException;

/** Don refusé (projet fermé, objectif presque atteint, paiement indisponible) : message affiché au citoyen. */
class DonationRefused extends RuntimeException {}
