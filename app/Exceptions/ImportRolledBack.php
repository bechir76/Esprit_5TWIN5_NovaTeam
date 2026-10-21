<?php

namespace App\Exceptions;

use RuntimeException;

/** Interrompt la transaction d'un import CSV « tout ou rien » qui contient au moins une erreur (InfrastructureImporter). */
class ImportRolledBack extends RuntimeException {}
