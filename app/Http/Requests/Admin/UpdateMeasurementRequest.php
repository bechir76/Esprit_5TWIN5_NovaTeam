<?php

namespace App\Http\Requests\Admin;

/** Mêmes règles que la création. L'ignore() dans StoreMeasurementRequest exclut la mesure en cours d'édition. */
class UpdateMeasurementRequest extends StoreMeasurementRequest {}
