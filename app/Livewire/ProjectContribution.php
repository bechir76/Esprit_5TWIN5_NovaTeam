<?php

namespace App\Livewire;

use App\Http\Requests\StoreContributionRequest;
use App\Models\Funding;
use App\Models\Project;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Carte « Contribuer » de la fiche publique d'un projet : montants rapides ou montant libre,
 * validation en temps réel et montant collecté / barre de progression mis à jour après la contribution.
 * Mêmes règles que la route POST projects.fundings.store : règles et messages de StoreContributionRequest,
 * Project::contributionRefusal() (projet public, en collecte, non échu), Project::recordContribution()
 * (le passage « en collecte » → « financé » est fait par le hook saved du financement).
 */
class ProjectContribution extends Component
{
    public Project $project;

    /** Montant rapide choisi (boutons 20 / 50 / 100 TND). */
    public string $amount = '50';

    /** « Autre montant » : remplace le montant rapide s'il est rempli. */
    public string $amountOther = '';

    /** Message de remerciement après la dernière contribution. */
    public ?string $thanks = null;

    /** @var list<int> */
    public array $presets = [20, 50, 100];

    public function mount(Project $project): void
    {
        $this->project = $project;

        // Retour de la route POST (navigateur sans JavaScript) : saisie et erreurs conservées
        $old = (string) old('amount', '50');
        $this->amount = in_array((int) $old, $this->presets, true) ? $old : '50';
        $this->amountOther = (string) old('amount_other', '');

        if (session()->has('errors')) {
            $this->setErrorBag(session('errors')->getBag('default'));
        }
    }

    public function updatedAmount(): void
    {
        $this->amountOther = '';
        $this->validateAmount();
    }

    public function updatedAmountOther(): void
    {
        $this->validateAmount();
    }

    public function contribute(): mixed
    {
        $user = auth()->user();

        if (! $user) {
            return $this->redirectRoute('login');
        }

        // Même règle que la route POST (middleware « verified ») : adresse e-mail vérifiée
        if (! $user->hasVerifiedEmail()) {
            return $this->redirectRoute('verification.notice');
        }

        $this->thanks = null;

        if ($refusal = $this->project->contributionRefusal()) {
            $this->addError('project', $refusal);

            return null;
        }

        $amount = $this->validateAmount();

        $funding = $this->project->recordContribution($user, $amount);

        $this->thanks = $funding->contributionThanks();
        $this->amountOther = '';
        $this->project->refresh();

        return null;
    }

    /** Montant envoyé : « Autre montant » s'il est saisi, sinon le montant rapide. */
    protected function resolvedAmount(): mixed
    {
        return Funding::normalizeAmount(filled($this->amountOther) ? $this->amountOther : $this->amount);
    }

    /** Valide le montant avec les règles de StoreContributionRequest et le renvoie. */
    protected function validateAmount(): mixed
    {
        $this->resetErrorBag('amount');

        return Validator::make(
            ['amount' => $this->resolvedAmount()],
            StoreContributionRequest::amountRules(),
            StoreContributionRequest::amountMessages(),
            ['amount' => 'montant'],
        )->validate()['amount'];
    }

    public function render(): View
    {
        return view('livewire.project-contribution');
    }
}
