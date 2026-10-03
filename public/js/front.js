/* AquaSecure Citoyens : JS extrait de templates-source/aquasecure-front.html.
   Chaque écran est une route Laravel : le routeur par ancre et les actions simulées du template ne sont pas repris.
   Le fichier est chargé sur toutes les pages : un élément absent de la page courante est ignoré. */
const $=id=>document.getElementById(id);
const fill=(id,h)=>{const e=$(id);if(e)e.innerHTML=h};
const on=(id,ev,f)=>{const e=$(id);if(e)e.addEventListener(ev,f)};
/* URL d'un écran : la vue peut définir window.AQUA_ROUTES = {projet: "{{ route('...') }}", ...}, sinon ancre */
const url=p=>(window.AQUA_ROUTES||{})[p]||"#"+p;

const incidents=[
 {ref:"INC-2026-0918",t:"Fuite importante rue Ibn Sina",type:"Fuite",zone:"Ariana",sev:["Élevée","b-crit"],st:["En cours","b-info"],d:"03/10/2026",ago:"il y a 2 h"},
 {ref:"INC-2026-0915",t:"Eau trouble et odeur de terre",type:"Contamination",zone:"Ben Arous",sev:["Critique","b-crit"],st:["Signalé","b-warn"],d:"03/10/2026",ago:"il y a 5 h"},
 {ref:"INC-2026-0907",t:"Coupure prolongée cité El Khadra",type:"Coupure",zone:"Tunis",sev:["Moyenne","b-warn"],st:["En cours","b-info"],d:"02/10/2026",ago:"hier"},
 {ref:"INC-2026-0899",t:"Suintement au pied du compteur",type:"Fuite",zone:"La Manouba",sev:["Faible","b-ok"],st:["Résolu","b-ok"],d:"01/10/2026",ago:"il y a 2 j"},
 {ref:"INC-2026-0884",t:"Pression très faible aux étages",type:"Coupure",zone:"Ariana",sev:["Moyenne","b-warn"],st:["Résolu","b-ok"],d:"29/09/2026",ago:"il y a 4 j"},
 {ref:"INC-2026-0871",t:"Conduite éclatée avenue de Carthage",type:"Fuite",zone:"Tunis",sev:["Critique","b-crit"],st:["Résolu","b-ok"],d:"27/09/2026",ago:"il y a 6 j"}
];
const icon={Fuite:["💧","var(--water-soft)"],Contamination:["⚠️","var(--crit-soft)"],Coupure:["🚱","var(--warn-soft)"]};
const projects=[
 {n:"Réhabilitation du réservoir de La Soukra",tag:["Décontamination","b-warn"],g:"linear-gradient(135deg,#0E5A85,#2EC4B6)",c:168400,o:220000,days:58,d:"Fissures du radier et dépôts de manganèse sur un réservoir de 5 000 m³."},
 {n:"Remplacement de 2,4 km de conduites en fonte",tag:["Rénovation","b-info"],g:"linear-gradient(135deg,#0B3C5D,#1CA7EC)",c:96000,o:310000,days:112,d:"Conduites de 1962 responsables de 40 % des fuites du quartier Bab Saadoun."},
 {n:"Capteurs de qualité dans 12 écoles",tag:["Surveillance","b-ok"],g:"linear-gradient(135deg,#126D73,#7FD8C9)",c:41500,o:48000,days:21,d:"Mesure en continu du chlore et de la turbidité aux points d'eau scolaires."},
 {n:"Station de pompage solaire de Raoued",tag:["Sécheresse","b-warn"],g:"linear-gradient(135deg,#3A5F7D,#F4A261)",c:22000,o:180000,days:140,d:"Maintenir la pression en été malgré les délestages électriques."},
 {n:"Traitement du nitrate au forage de Mornag",tag:["Décontamination","b-crit"],g:"linear-gradient(135deg,#5B2333,#E63946)",c:133000,o:150000,days:9,d:"Taux de nitrate mesuré à 62 mg/L, au-dessus du seuil de 50 mg/L."},
 {n:"Sectorisation du réseau de Ben Arous",tag:["Rénovation","b-info"],g:"linear-gradient(135deg,#0F4C6E,#5CC8F0)",c:57000,o:95000,days:76,d:"Débitmètres de secteur pour localiser les fuites invisibles."}
];
const fmt=n=>n.toLocaleString("fr-FR").replace(/ /g," ");
const projCard=p=>{const pc=Math.round(p.c/p.o*100);return `<a class="card proj-card" href="${url("projet")}" style="text-decoration:none"><div class="proj-img" style="background:${p.g}"><span class="badge ${p.tag[1]}">${p.tag[0]}</span></div><div class="proj-body"><h3>${p.n}</h3><p class="muted" style="font-size:.9rem">${p.d}</p><div style="margin-top:auto" class="stack"><div class="progress"><span style="width:${pc}%"></span></div><div class="money"><span><strong class="mono">${fmt(p.c)}</strong> / <span class="mono">${fmt(p.o)} TND</span></span><span class="muted">${pc} % · ${p.days} j</span></div></div></div></a>`};
fill("homeProjects",projects.slice(0,3).map(projCard).join(""));
fill("allProjects",projects.map(projCard).join(""));
fill("homeIncidents",incidents.slice(0,4).map(i=>`<div class="inc-item"><div class="inc-ic" style="background:${icon[i.type][1]}">${icon[i.type][0]}</div><div style="flex:1;min-width:0"><strong>${i.t}</strong><div class="muted" style="font-size:.85rem">${i.zone} · ${i.ago}</div></div><span class="badge ${i.st[1]}">${i.st[0]}</span></div>`).join(""));
fill("myReports",incidents.filter((_,k)=>[0,3,4].includes(k)).map(i=>`<div class="inc-item"><div class="inc-ic" style="background:${icon[i.type][1]}">${icon[i.type][0]}</div><div style="flex:1;min-width:0"><strong>${i.t}</strong><div class="muted mono" style="font-size:.8rem">${i.ref} · ${i.d}</div></div><span class="badge ${i.st[1]}">${i.st[0]}</span></div>`).join(""));
fill("incTable",incidents.map(i=>`<tr><td class="mono" style="font-size:.85rem">${i.ref}</td><td><strong>${i.t}</strong></td><td>${icon[i.type][0]} ${i.type}</td><td>${i.zone}</td><td><span class="badge ${i.sev[1]}">${i.sev[0]}</span></td><td><span class="badge ${i.st[1]}">${i.st[0]}</span></td><td class="mono">${i.d}</td></tr>`).join(""));

/* Carte schématique */
const sites=[
 {x:180,y:150,s:"ok",n:"Réservoir Belvédère",t:"Réservoir · 12 000 m³",z:"Tunis Centre",i:"14/09/2026",l:"Niveau 78 %"},
 {x:420,y:110,s:"warn",n:"Station de pompage Ariana Nord",t:"Pompage · 3 pompes",z:"Ariana",i:"02/08/2026",l:"Pression 2,4 bar"},
 {x:640,y:180,s:"crit",n:"Réservoir de La Soukra",t:"Réservoir · 5 000 m³",z:"Ariana",i:"12/03/2026",l:"Manganèse 0,12 mg/L"},
 {x:300,y:330,s:"ok",n:"Station de traitement Ghdir El Golla",t:"Traitement · 4 m³/s",z:"La Manouba",i:"28/09/2026",l:"Chlore 0,42 mg/L"},
 {x:560,y:380,s:"warn",n:"Conduite DN 600 Ben Arous",t:"Conduite principale · 4,1 km",z:"Ben Arous",i:"19/07/2026",l:"Pertes 6 m³/h"},
 {x:760,y:430,s:"ok",n:"Réservoir Mégrine",t:"Réservoir · 8 000 m³",z:"Ben Arous",i:"01/09/2026",l:"Niveau 64 %"},
 {x:120,y:440,s:"crit",n:"Forage de Mornag F3",t:"Forage · 35 L/s",z:"Ben Arous",i:"21/09/2026",l:"Nitrate 62 mg/L"}
];
const col={ok:"var(--teal)",warn:"var(--warn)",crit:"var(--crit)"},lbl={ok:["Bon","b-ok"],warn:["À surveiller","b-warn"],crit:["Critique","b-crit"]};
let svg=`<rect width="900" height="560" fill="var(--surface-2)"/>
<path d="M620 0 C700 60 820 70 900 50 L900 0Z" fill="var(--water-soft)"/>
<path d="M0 260 C200 240 380 300 900 280" stroke="var(--line)" stroke-width="16" fill="none"/>
<path d="M260 0 C280 200 240 400 300 560" stroke="var(--line)" stroke-width="12" fill="none"/>
<path d="M600 0 C580 200 640 380 620 560" stroke="var(--line)" stroke-width="12" fill="none"/>
<path d="M0 470 L900 420" stroke="var(--line)" stroke-width="10" fill="none"/>
<g stroke="var(--water)" stroke-width="3" fill="none" opacity=".55" stroke-dasharray="2 0">
<path d="M300 330 L180 150 L420 110 L640 180"/><path d="M300 330 L560 380 L760 430"/><path d="M300 330 L120 440"/></g>
<text x="40" y="40" fill="var(--muted)" font-family="IBM Plex Mono,monospace" font-size="12">TUNIS</text>
<text x="440" y="60" fill="var(--muted)" font-family="IBM Plex Mono,monospace" font-size="12">ARIANA</text>
<text x="660" y="520" fill="var(--muted)" font-family="IBM Plex Mono,monospace" font-size="12">BEN AROUS</text>
<text x="40" y="520" fill="var(--muted)" font-family="IBM Plex Mono,monospace" font-size="12">MORNAG</text>`;
sites.forEach((s,k)=>{svg+=`<g class="marker" data-k="${k}" tabindex="0"><circle cx="${s.x}" cy="${s.y}" r="13" fill="${col[s.s]}" opacity=".22"/><circle cx="${s.x}" cy="${s.y}" r="7" fill="${col[s.s]}" stroke="#fff" stroke-width="2.5"/></g>`});
fill("mapSvg",svg);
const pop=$("mapPop");
function showSite(k){const s=sites[k];if(!pop||!s)return;pop.innerHTML=`<div class="row" style="justify-content:space-between"><span class="badge ${lbl[s.s][1]}">${lbl[s.s][0]}</span><span class="muted mono" style="font-size:.75rem">${s.z}</span></div><h3>${s.n}</h3><div class="muted" style="font-size:.88rem">${s.t}</div><div class="budget-row"><span>Dernière inspection</span><span class="mono">${s.i}</span></div><div class="budget-row" style="border:0"><span>Mesure</span><span class="mono">${s.l}</span></div><a href="${url("signaler")}" class="btn btn-ghost btn-sm" style="justify-content:center">Signaler un problème ici</a>`}
showSite(2);
on("mapSvg","click",e=>{const g=e.target.closest(".marker");if(g)showSite(+g.dataset.k)});
on("mapSvg","keydown",e=>{const g=e.target.closest(".marker");if(g&&e.key==="Enter")showSite(+g.dataset.k)});

/* Lien actif du menu : l'écran courant est la section [data-page] rendue par la vue */
const cur=(document.querySelector("[data-page]")||{dataset:{}}).dataset.page;
if(cur)document.querySelectorAll("[data-nav]").forEach(a=>a.classList.toggle("active",a.dataset.nav===cur||(cur==="projet"&&a.dataset.nav==="projets")));

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
document.querySelectorAll("[data-toast]").forEach(b=>b.addEventListener("click",()=>showToast(b.dataset.toast)));
on("reportForm","submit",e=>{e.preventDefault();showToast("Signalement INC-2026-0921 envoyé. Vous serez notifiée de son traitement.")});

document.querySelectorAll(".chips button").forEach(c=>c.addEventListener("click",()=>{const ci=$("chatIn"),cf=$("chatForm");if(!ci||!cf)return;ci.value=c.textContent;cf.requestSubmit()}));
/* Ondulations du hero */
(function(){const c=$("ripple");if(!c||!c.getContext)return;const x=c.getContext("2d");if(matchMedia("(prefers-reduced-motion: reduce)").matches)return;
let w,h,t=0;function rs(){w=c.width=c.offsetWidth*devicePixelRatio;h=c.height=c.offsetHeight*devicePixelRatio}rs();addEventListener("resize",rs);
(function f(){x.clearRect(0,0,w,h);x.strokeStyle="rgba(127,216,255,.5)";x.lineWidth=1.2*devicePixelRatio;
for(let i=0;i<9;i++){x.beginPath();for(let px=0;px<=w;px+=12){const y=h*(.15+i*.1)+Math.sin(px/140/devicePixelRatio+t+i*.7)*10*devicePixelRatio;px?x.lineTo(px,y):x.moveTo(px,y)}x.stroke()}
t+=.012;requestAnimationFrame(f)})()})();
