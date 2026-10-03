{{-- Modale ouverte par un bouton [data-del]. S'il porte data-action="{{ route('...destroy', ...) }}",
     « Supprimer » envoie le formulaire DELETE ci-dessous vers cette URL ; sinon, simple toast. --}}
<div class="overlay" id="modal" hidden role="dialog" aria-modal="true" aria-labelledby="mTitle">
  <div class="modal">
    <div class="ic">🗑</div>
    <h2 id="mTitle">Supprimer cet élément ?</h2>
    <p class="muted" id="mText">Cette action est définitive.</p>
    <form method="POST" id="mForm" hidden>
      @csrf
      @method('DELETE')
    </form>
    <div class="row" style="justify-content:flex-end"><button class="btn btn-ghost" type="button" id="mCancel">Annuler</button><button class="btn btn-danger" type="button" id="mOk">Supprimer</button></div>
  </div>
</div>
