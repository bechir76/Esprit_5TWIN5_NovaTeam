{{-- Modale ouverte par un bouton [data-del]. S'il porte data-action="{{ route('...destroy', ...) }}",
     « Supprimer » envoie le formulaire DELETE ci-dessous vers cette URL ; sinon, simple toast (back.js). --}}
<div class="fixed inset-0 z-50 grid place-items-center bg-[rgba(6,24,36,.55)] p-4" id="modal" hidden role="dialog" aria-modal="true" aria-labelledby="mTitle">
  <div class="flex w-full max-w-[440px] flex-col gap-3.5 rounded-panel bg-surface p-6 shadow-modal">
    <div class="grid size-[46px] place-items-center rounded-xl bg-crit-soft text-[1.3rem] text-crit-fg">🗑</div>
    <h2 id="mTitle">Supprimer cet élément ?</h2>
    <p class="text-muted" id="mText">Cette action est définitive.</p>
    <form method="POST" id="mForm" hidden>
      @csrf
      @method('DELETE')
    </form>
    <div class="flex justify-end gap-2.5">
      <button class="btn btn-ghost" type="button" id="mCancel">Annuler</button>
      <button class="btn btn-danger" type="button" id="mOk">Supprimer</button>
    </div>
  </div>
</div>
