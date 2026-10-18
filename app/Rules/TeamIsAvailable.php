<?php

namespace App\Rules;

use App\Enums\InterventionStatus;
use App\Services\Incidents\InterventionScheduler;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * L'équipe n'a pas d'autre intervention planifiée ou en cours sur le même créneau
 * (date prévue + durée estimée). Vérifiée de nouveau dans la transaction par InterventionScheduler.
 */
class TeamIsAvailable implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private ?int $ignoreId = null) {}

    /** @param  array<string, mixed>  $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $status = InterventionStatus::tryFrom((string) ($this->data['status'] ?? ''));

        if (! $status?->isActive() || ! is_string($value) || ! is_numeric($this->data['estimated_hours'] ?? null)) {
            return;
        }

        try {
            $start = Carbon::parse($this->data['scheduled_at'] ?? null);
        } catch (\Throwable) {
            return; // date invalide : signalée par la règle « date »
        }

        if ($message = app(InterventionScheduler::class)->conflictMessage($value, $start, (float) $this->data['estimated_hours'], $this->ignoreId)) {
            $fail($message);
        }
    }
}
