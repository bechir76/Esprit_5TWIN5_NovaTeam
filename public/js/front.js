/* AquaSecure Citoyens : JS extrait de templates-source/aquasecure-front.html.
   Chaque écran est une route Laravel : le routeur par ancre et les actions simulées du template ne sont pas repris.
   Le fichier est chargé sur toutes les pages : un élément absent de la page courante est ignoré. */
const $=id=>document.getElementById(id);
const fill=(id,h)=>{const e=$(id);if(e)e.innerHTML=h};
const on=(id,ev,f)=>{const e=$(id);if(e)e.addEventListener(ev,f)};
/* URL d'un écran : la vue peut définir window.AQUA_ROUTES = {projet: "{{ route('...') }}", ...}, sinon ancre */
const url=p=>(window.AQUA_ROUTES||{})[p]||"#"+p;

/* Les cartes projets (accueil, /projets) sont rendues par Blade (<x-project-card>) à partir de la base : plus de données de démonstration ici */

/* Carte schématique : infrastructures publiques envoyées par InfrastructureMapController (window.AQUA_SITES),
   placées par projection linéaire de leurs coordonnées dans l'emprise window.AQUA_MAP_BOUNDS */
const sites=window.AQUA_SITES||[],bounds=window.AQUA_MAP_BOUNDS||{lat:[36.70,36.95],lng:[10.05,10.35]};
const esc=v=>String(v??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]));
const project=(lat,lng)=>{const c=(v,a,b)=>Math.min(1,Math.max(0,(v-a)/(b-a)));return [40+c(lng,bounds.lng[0],bounds.lng[1])*820,40+(1-c(lat,bounds.lat[0],bounds.lat[1]))*480]};
const mapLabel=(t,lat,lng)=>{const [x,y]=project(lat,lng);return `<text x="${x.toFixed(0)}" y="${y.toFixed(0)}" fill="var(--muted)" font-family="IBM Plex Mono,monospace" font-size="12" text-anchor="middle">${t}</text>`};
let svg=`<rect width="900" height="560" fill="var(--surface-2)"/>
<path d="M620 0 C700 60 820 70 900 50 L900 0Z" fill="var(--water-soft)"/>
<path d="M0 260 C200 240 380 300 900 280" stroke="var(--line)" stroke-width="16" fill="none"/>
<path d="M260 0 C280 200 240 400 300 560" stroke="var(--line)" stroke-width="12" fill="none"/>
<path d="M600 0 C580 200 640 380 620 560" stroke="var(--line)" stroke-width="12" fill="none"/>
<path d="M0 470 L900 420" stroke="var(--line)" stroke-width="10" fill="none"/>`
+mapLabel("TUNIS",36.790,10.160)+mapLabel("ARIANA",36.905,10.150)+mapLabel("BEN AROUS",36.735,10.200)+mapLabel("LA MANOUBA",36.835,10.080)+mapLabel("MORNAG",36.690,10.300);
sites.forEach((s,k)=>{const [x,y]=project(s.lat,s.lng);svg+=`<g class="marker" data-k="${k}" tabindex="0" role="button" aria-label="${esc(s.name)}"><circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="13" fill="${esc(s.color)}" opacity=".22"/><circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="7" fill="${esc(s.color)}" stroke="#fff" stroke-width="2.5"/></g>`});
fill("mapSvg",svg);
const pop=$("mapPop");
function showSite(k){const s=sites[k];if(!pop||!s)return;pop.innerHTML=`<div class="row" style="justify-content:space-between"><span class="badge ${esc(s.badge)}">${esc(s.statusLabel)}</span><span class="muted mono" style="font-size:.75rem">${esc(s.zone)}</span></div><h3>${esc(s.name)}</h3><div class="muted" style="font-size:.88rem">${esc(s.type)} · ${esc(s.capacity)}</div><div class="budget-row"><span>Dernière inspection</span><span class="mono">${esc(s.inspection)}</span></div><div class="budget-row" style="border:0"><span>Code ouvrage</span><span class="mono">${esc(s.code)}</span></div><a href="${url("signaler")}" class="btn btn-ghost btn-sm" style="justify-content:center">Signaler un problème ici</a>`}
/* Fiche affichée par défaut : le premier ouvrage critique, sinon le premier */
if(pop){if(sites.length){const c=sites.findIndex(s=>s.status==="critique");showSite(c<0?0:c)}else pop.innerHTML=`<h3>Aucun ouvrage</h3><div class="muted" style="font-size:.88rem">Aucune infrastructure publique ne correspond à ces filtres.</div>`}
on("mapSvg","click",e=>{const g=e.target.closest(".marker");if(g)showSite(+g.dataset.k)});
on("mapSvg","keydown",e=>{const g=e.target.closest(".marker");if(g&&e.key==="Enter")showSite(+g.dataset.k)});

/* Interactions */
on("burger","click",()=>{const n=$("nav");if(n)n.classList.toggle("open")});
const ab=$("avatarBtn"),ud=$("userDrop");
if(ab&&ud)ab.addEventListener("click",e=>{e.stopPropagation();ud.hidden=!ud.hidden;ab.setAttribute("aria-expanded",!ud.hidden)});
document.addEventListener("click",e=>{if(ud&&!e.target.closest(".user-menu"))ud.hidden=true});
const cw=$("chatWin");
on("chatFab","click",()=>{if(cw)cw.hidden=!cw.hidden});
on("chatClose","click",()=>{if(cw)cw.hidden=true});
on("chatForm","submit",e=>{e.preventDefault();const ci=$("chatIn"),b=$("chatBody");if(!ci||!b)return;const v=ci.value.trim();if(!v)return;b.insertAdjacentHTML("beforeend",`<div class="msg me"></div>`);b.lastElementChild.textContent=v;setTimeout(()=>{b.insertAdjacentHTML("beforeend",`<div class="msg bot">Je vérifie les données de votre commune… (réponse simulée dans ce template)</div>`);b.scrollTop=b.scrollHeight},500);ci.value="";b.scrollTop=b.scrollHeight});
const toast=$("toast");let tt;
function showToast(m){if(!toast)return;toast.textContent="✓ "+m;toast.hidden=false;clearTimeout(tt);tt=setTimeout(()=>toast.hidden=true,2800)}
/* Message flash rendu par Laravel (session status) : masqué comme les autres toasts */
if(toast&&!toast.hidden)tt=setTimeout(()=>toast.hidden=true,2800);
document.querySelectorAll("[data-toast]").forEach(b=>b.addEventListener("click",()=>showToast(b.dataset.toast)));

document.querySelectorAll(".chips button").forEach(c=>c.addEventListener("click",()=>{const ci=$("chatIn"),cf=$("chatForm");if(!ci||!cf)return;ci.value=c.textContent;cf.requestSubmit()}));
/* Ondulations du hero */
(function(){const c=$("ripple");if(!c||!c.getContext)return;const x=c.getContext("2d");if(matchMedia("(prefers-reduced-motion: reduce)").matches)return;
let w,h,t=0;function rs(){w=c.width=c.offsetWidth*devicePixelRatio;h=c.height=c.offsetHeight*devicePixelRatio}rs();addEventListener("resize",rs);
(function f(){x.clearRect(0,0,w,h);x.strokeStyle="rgba(127,216,255,.5)";x.lineWidth=1.2*devicePixelRatio;
for(let i=0;i<9;i++){x.beginPath();for(let px=0;px<=w;px+=12){const y=h*(.15+i*.1)+Math.sin(px/140/devicePixelRatio+t+i*.7)*10*devicePixelRatio;px?x.lineTo(px,y):x.moveTo(px,y)}x.stroke()}
t+=.012;requestAnimationFrame(f)})()})();
/* Formulaire de signalement : « Utiliser ma position » remplit latitude / longitude, nom de la photo choisie */
on("geoBtn","click",()=>{const la=$("latitude"),lo=$("longitude");if(!la||!lo||!navigator.geolocation){showToast("Géolocalisation indisponible sur cet appareil.");return}
 navigator.geolocation.getCurrentPosition(p=>{la.value=p.coords.latitude.toFixed(6);lo.value=p.coords.longitude.toFixed(6);showToast("Position ajoutée au signalement.")},()=>showToast("Position non disponible : saisissez l'adresse."))});
on("photo","change",e=>{const n=$("photoName"),f=e.target.files[0];if(n)n.textContent=f?f.name:""});
