<footer class="bg-navy pt-12 pb-7 text-[#B8D2E3]">
  <div class="mx-auto max-w-[1200px] px-5">
    <div class="grid grid-cols-2 gap-7 min-[761px]:grid-cols-[2fr_1fr_1fr_1fr]">
      <div class="col-span-2 flex flex-col gap-3 min-[761px]:col-span-1">
        <span class="flex items-center gap-2.5 font-display text-[1.15rem] font-extrabold text-white"><svg width="28" height="28" viewBox="0 0 32 32" aria-hidden="true"><path d="M16 3C16 3 6 14.5 6 20.5a10 10 0 0 0 20 0C26 14.5 16 3 16 3Z" fill="#1CA7EC"/></svg>AquaSecure</span>
        <p class="max-w-[38ch] text-[.92rem]">Plateforme citoyenne de surveillance des infrastructures d'eau potable, en lien avec les municipalités et les régies des eaux.</p>
      </div>
      @foreach ([
          'Plateforme' => [[route('map'), 'Carte du réseau'], [route('incidents.create'), 'Signaler un incident'], [route('projects.index'), 'Projets']],
          'Ressources' => [[route('water-quality'), 'Qualité de l\'eau'], [route('home'), 'Gestes économes'], [route('home'), 'FAQ']],
      ] as $title => $links)
      <div>
        <h4 class="mb-3 text-[.95rem] text-white">{{ $title }}</h4>
        @foreach ($links as [$href, $label])
        <a class="block py-1 text-[.92rem] hover:text-white" href="{{ $href }}">{{ $label }}</a>
        @endforeach
      </div>
      @endforeach
      <div>
        <h4 class="mb-3 text-[.95rem] text-white">Contact</h4>
        <span class="block py-1 text-[.92rem]">Numéro vert : 80 100 319</span>
        <span class="block py-1 text-[.92rem]">contact@aquasecure.tn</span>
      </div>
    </div>
    <div class="mt-8 flex flex-wrap justify-between gap-3 border-t border-white/12 pt-[18px] text-[.82rem]">
      <span>© 2026 AquaSecure · Projet Laravel 12</span><span>Mentions légales · Confidentialité</span>
    </div>
  </div>
</footer>
