/* AquaSecure Gestion : JS extrait de templates-source/aquasecure-back.html.
   Chaque écran est une route Laravel : le routeur par ancre et les actions simulées du template ne sont pas repris.
   Le fichier est chargé sur toutes les pages : un élément absent de la page courante est ignoré. */
const $=id=>document.getElementById(id);
const fill=(id,h)=>{const e=$(id);if(e)e.innerHTML=h};
const on=(id,ev,f)=>{const e=$(id);if(e)e.addEventListener(ev,f)};
/* URL d'un écran : la vue peut définir window.AQUA_ROUTES = {incident: "{{ route('...') }}", ...}, sinon ancre */
const url=p=>(window.AQUA_ROUTES||{})[p]||"#"+p;

const I={
 dash:'<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
 infra:'<path d="M4 20V9l8-5 8 5v11"/><path d="M9 20v-6h6v6"/>',
 inc:'<path d="M12 3 2 20h20Z"/><path d="M12 10v4M12 17h.01"/>',
 tool:'<path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4Z"/>',
 proj:'<path d="M3 7h18v13H3zM8 7V4h8v3"/>',
 money:'<circle cx="12" cy="12" r="9"/><path d="M15 9h-4a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9M12 6v2M12 16v2"/>',
 ai:'<path d="M12 3v3M12 18v3M3 12h3M18 12h3M6 6l2 2M16 16l2 2M6 18l2-2M16 8l2-2"/><circle cx="12" cy="12" r="3"/>',
 users:'<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0M17 11a3 3 0 1 0 0-6M22 21a6 6 0 0 0-4-5.7"/>',
 shield:'<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6Z"/><path d="m9 12 2 2 4-4"/>',
 cog:'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
 eye:'<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
 edit:'<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
 trash:'<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
 ban:'<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>'
};
const ico=(k,s=17)=>`<svg width="${s}" height="${s}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">${I[k]}</svg>`;
/* Sparklines des cartes <x-kpi-card :spark="[...]"> (attributs data-spark et data-color) */
const spark=(arr,c)=>{const mx=Math.max(...arr),mn=Math.min(...arr),w=160,h=30;const p=arr.map((v,i)=>[i*(w/(arr.length-1)),h-3-((v-mn)/(mx-mn||1))*(h-6)]);const d=p.map((q,i)=>(i?"L":"M")+q[0].toFixed(1)+" "+q[1].toFixed(1)).join(" ");const last=p[p.length-1];return `<svg viewBox="0 0 ${w} ${h}" preserveAspectRatio="none"><path d="${d} L${w} ${h} L0 ${h}Z" fill="${c}" opacity=".12"/><path d="${d}" fill="none" stroke="${c}" stroke-width="1.8"/><circle cx="${last[0]}" cy="${last[1]}" r="2.8" fill="${c}"/></svg>`};
document.querySelectorAll("[data-spark]").forEach(e=>e.insertAdjacentHTML("beforeend",spark(e.dataset.spark.split(",").map(Number),e.dataset.color)));

/* Bar chart */
(function(){const m=["Avr","Mai","Juin","Juil","Août","Sep"],a=[42,51,68,84,91,73],b=[38,47,59,70,86,69];const W=560,H=240,L=36,B=26,T=10,max=100;const sx=(W-L-10)/m.length;let s="";
for(let v=0;v<=max;v+=25){const y=T+(H-T-B)*(1-v/max);s+=`<line class="grid-l" x1="${L}" x2="${W-6}" y1="${y}" y2="${y}"/><text x="${L-8}" y="${y+4}" text-anchor="end">${v}</text>`}
m.forEach((mm,i)=>{const x=L+i*sx+sx*.18,bw=sx*.3;[[a[i],"var(--water)",0],[b[i],"var(--teal)",bw+3]].forEach(([v,c,o])=>{const h=(H-T-B)*v/max;s+=`<rect x="${x+o}" y="${H-B-h}" width="${bw}" height="${h}" rx="3" fill="${c}"><title>${mm} : ${v}</title></rect>`});s+=`<text x="${x+bw+1.5}" y="${H-8}" text-anchor="middle">${mm}</text>`});
fill("barChart",s)})();
/* Donut */
(function(){const d=[["Fuites",15,"var(--water)"],["Coupures",8,"var(--warn)"],["Contamination",4,"var(--crit)"]],tot=27;let off=25,s=`<circle cx="21" cy="21" r="15.9" fill="none" stroke="var(--surface-2)" stroke-width="6"/>`;
d.forEach(x=>{const p=x[1]/tot*100;s+=`<circle cx="21" cy="21" r="15.9" fill="none" stroke="${x[2]}" stroke-width="6" stroke-dasharray="${p-.8} ${100-p+.8}" stroke-dashoffset="${off}"/>`;off-=p});
s+=`<text x="21" y="21" text-anchor="middle" font-family="Manrope,sans-serif" font-weight="800" font-size="8" fill="var(--fg)">27</text><text x="21" y="27" text-anchor="middle" font-size="3.2" fill="var(--muted)" font-family="IBM Plex Mono,monospace">OUVERTS</text>`;
fill("donut",s);fill("donutLegend",d.map(x=>`<li><span><i style="display:inline-block;width:10px;height:10px;border-radius:3px;background:${x[2]};margin-right:8px"></i>${x[0]}</span><span class="mono">${x[1]} · ${Math.round(x[1]/tot*100)} %</span></li>`).join(""))})();

/* Données */
const sevC={Critique:["b-crit","var(--crit)"],"Élevée":["b-crit","var(--crit)"],Moyenne:["b-warn","var(--warn)"],Faible:["b-ok","var(--teal)"]};
const stC={"Signalé":"b-warn","Qualifié":"b-info","En intervention":"b-info","Résolu":"b-ok"};
const inc=[["INC-2026-0918","Fuite importante rue Ibn Sina","💧 Fuite","Conduite DN 300 Ariana Nord","Élevée","En intervention","Réseau · Ariana","03/10 09:02"],
 ["INC-2026-0915","Eau trouble et odeur de terre","⚠️ Contamination","Forage de Mornag F3","Critique","Signalé","—","03/10 07:12"],
 ["INC-2026-0912","Chute de pression secteur nord","🚱 Coupure","Station de pompage Ariana Nord","Critique","Qualifié","Astreinte","03/10 02:40"],
 ["INC-2026-0907","Coupure prolongée cité El Khadra","🚱 Coupure","Vanne V-114 Tunis","Moyenne","En intervention","Réseau · Tunis","02/10 16:25"],
 ["INC-2026-0899","Suintement au pied du compteur","💧 Fuite","Branchement Manouba","Faible","Résolu","Astreinte","01/10 11:08"],
 ["INC-2026-0884","Pression faible aux étages","🚱 Coupure","Réservoir Mégrine","Moyenne","Résolu","Réseau · Ben Arous","29/09 18:30"],
 ["INC-2026-0871","Conduite éclatée av. de Carthage","💧 Fuite","Conduite DN 600 Tunis","Critique","Résolu","Réseau · Tunis","27/09 06:55"]];
fill("incTable",inc.map(r=>`<tr><td><span class="sev-bar" style="background:${sevC[r[4]][1]}"></span><span style="display:inline-block;vertical-align:middle"><a href="${url("incident")}" style="font-weight:600;text-decoration:none">${r[1]}</a><br><span class="mono muted" style="font-size:.76rem">${r[0]}</span></span></td><td>${r[2]}</td><td>${r[3]}</td><td><span class="badge ${sevC[r[4]][0]} dot">${r[4]}</span></td><td><span class="badge ${stC[r[5]]}">${r[5]}</span></td><td class="muted">${r[6]}</td><td class="mono">${r[7]}</td><td><div class="acts"><a class="act" href="${url("incident")}" title="Voir">${ico("eye",15)}</a><a class="act" href="${url("incident")}" title="Modifier">${ico("edit",15)}</a><button class="act del" data-del="${r[0]}" title="Supprimer">${ico("trash",15)}</button></div></td></tr>`).join(""));
fill("dashInc",inc.slice(0,5).map(r=>`<tr><td style="border-top:0;border-bottom:1px solid var(--line)"><span class="sev-bar" style="background:${sevC[r[4]][1]}"></span><span style="display:inline-block;vertical-align:middle"><a href="${url("incident")}" style="font-weight:600;text-decoration:none">${r[1]}</a><br><span class="muted" style="font-size:.78rem">${r[3]}</span></span></td><td style="border-top:0;border-bottom:1px solid var(--line)"><span class="badge ${stC[r[5]]}">${r[5]}</span></td><td class="mono muted" style="border-top:0;border-bottom:1px solid var(--line);font-size:.8rem;text-align:right">${r[7]}</td></tr>`).join(""));
const stI={Bon:"b-ok","À surveiller":"b-warn",Critique:"b-crit"};
const infra=[["RES-TUN-001","Réservoir Belvédère","Réservoir","Tunis","12 000 m³","Bon","14/09/2026"],["PMP-ARI-003","Station de pompage Ariana Nord","Station de pompage","Ariana","3 × 180 m³/h","Critique","02/08/2026"],["RES-ARI-004","Réservoir de La Soukra","Réservoir","Ariana","5 000 m³","Critique","12/03/2026"],["TRT-MAN-001","Station Ghdir El Golla","Station de traitement","La Manouba","4 m³/s","Bon","28/09/2026"],["CND-BAR-017","Conduite DN 600 Ben Arous","Conduite","Ben Arous","4,1 km","À surveiller","19/07/2026"],["RES-BAR-002","Réservoir Mégrine","Réservoir","Ben Arous","8 000 m³","Bon","01/09/2026"],["FOR-BAR-003","Forage de Mornag F3","Forage","Ben Arous","35 L/s","Critique","21/09/2026"],["CND-TUN-021","Conduite fonte Bab Saadoun","Conduite","Tunis","2,4 km","À surveiller","05/06/2026"]];
fill("infraTable",infra.map(r=>`<tr><td class="mono" style="font-size:.8rem">${r[0]}</td><td><strong>${r[1]}</strong></td><td>${r[2]}</td><td>${r[3]}</td><td class="mono">${r[4]}</td><td><span class="badge ${stI[r[5]]} dot">${r[5]}</span></td><td class="mono">${r[6]}</td><td><div class="acts"><a class="act" href="${url("infra-form")}" title="Voir">${ico("eye",15)}</a><a class="act" href="${url("infra-form")}" title="Modifier">${ico("edit",15)}</a><button class="act del" data-del="${r[1]}" title="Supprimer">${ico("trash",15)}</button></div></td></tr>`).join(""));
const fmt=n=>n.toLocaleString("fr-FR").replace(/ /g," ");
const proj=[["Réhabilitation du réservoir de La Soukra","RES-ARI-004",220000,168400,"30/11/2026","En collecte","b-info"],["Remplacement conduites Bab Saadoun","CND-TUN-021",310000,96000,"22/01/2027","En collecte","b-info"],["Capteurs qualité dans 12 écoles","Multi-sites",48000,48000,"24/10/2026","Financé","b-ok"],["Station de pompage solaire de Raoued","PMP-ARI-007",180000,22000,"20/02/2027","En collecte","b-info"],["Traitement nitrate forage Mornag","FOR-BAR-003",150000,133000,"12/10/2026","En travaux","b-warn"],["Sectorisation réseau Ben Arous","CND-BAR-017",95000,57000,"18/12/2026","Brouillon","b-mute"]];
fill("projTable",proj.map(p=>{const pc=Math.round(p[3]/p[2]*100);return `<tr><td><strong>${p[0]}</strong></td><td class="mono" style="font-size:.8rem">${p[1]}</td><td class="mono">${fmt(p[2])} TND</td><td style="min-width:160px"><div class="progress"><span style="width:${pc}%"></span></div><span class="mono muted" style="font-size:.76rem">${fmt(p[3])} · ${pc} %</span></td><td class="mono">${p[4]}</td><td><span class="badge ${p[6]}">${p[5]}</span></td><td><div class="acts"><a class="act" href="${url("financements")}" title="Financements">${ico("money",15)}</a><a class="act" href="${url("projet-form")}" title="Modifier">${ico("edit",15)}</a><button class="act del" data-del="${p[0]}" title="Supprimer">${ico("trash",15)}</button></div></td></tr>`}).join(""));
const progHtml=proj.slice(0,4).map(p=>{const pc=Math.round(p[3]/p[2]*100);return `<div class="stack" style="gap:6px"><div class="row between"><strong style="font-size:.88rem">${p[0]}</strong><span class="mono muted" style="font-size:.8rem">${pc} %</span></div><div class="progress"><span style="width:${pc}%"></span></div><span class="muted mono" style="font-size:.76rem">${fmt(p[3])} / ${fmt(p[2])} TND</span></div>`}).join("");
fill("dashProj",progHtml);fill("fundProg",progHtml);
const users=[["Karim Trabelsi","k.trabelsi@regie-ariana.tn","Administrateur","Régie des eaux d'Ariana","Actif","12/01/2025","#0E4A72"],["Amira Haddad","a.haddad@regie-ariana.tn","Gestionnaire réseau","Régie des eaux d'Ariana","Actif","03/03/2025","#1CA7EC"],["Youssef Mansour","y.mansour@tunis.gov.tn","Gestionnaire réseau","Municipalité de Tunis","Actif","18/04/2025","#2EC4B6"],["Nour Jlassi","n.jlassi@regie-ariana.tn","Technicien terrain","Régie des eaux d'Ariana","Actif","09/06/2025","#F4A261"],["Sana Ben Ali","sana.benali@mail.tn","Citoyen","—","Actif","14/02/2025","#2EC4B6"],["Mehdi Gharbi","mehdi.gharbi@mail.tn","Citoyen","—","Actif","22/08/2026","#5D7689"],["Walid Saidi","w.saidi@mail.tn","Citoyen","—","Suspendu","30/05/2026","#E63946"]];
const roleC={"Administrateur":"b-crit","Gestionnaire réseau":"b-info","Technicien terrain":"b-warn","Citoyen":"b-mute"};
fill("userTable",users.map(u=>`<tr><td><div class="row" style="flex-wrap:nowrap"><span class="av" style="background:${u[6]}">${u[0].split(" ").map(w=>w[0]).slice(0,2).join("")}</span><span><strong>${u[0]}</strong><br><span class="muted" style="font-size:.8rem">${u[1]}</span></span></div></td><td><span class="badge ${roleC[u[2]]}">${u[2]}</span></td><td>${u[3]}</td><td><span class="badge ${u[4]==="Actif"?"b-ok":"b-crit"} dot">${u[4]}</span></td><td class="mono">${u[5]}</td><td><div class="acts"><a class="act" href="${url("user-form")}" title="Modifier">${ico("edit",15)}</a><button class="act" title="${u[4]==="Actif"?"Suspendre":"Réactiver"}" data-toast="Compte de ${u[0]} ${u[4]==="Actif"?"suspendu":"réactivé"}.">${ico("ban",15)}</button><button class="act del" data-del="${u[0]}" title="Supprimer">${ico("trash",15)}</button></div></td></tr>`).join(""));
const mods=[["Infrastructures",[1,1,1,0]],["Incidents",[1,1,1,1]],["Interventions",[1,1,1,0]],["Projets",[1,1,1,0]],["Financements",[1,1,0,0]],["Analyses IA",[1,0,0,0]],["Utilisateurs",[1,0,0,0]]];
fill("matrix",mods.map((m,i)=>`<tr><td><strong>${m[0]}</strong></td>${m[1].map((v,j)=>`<td><input type="checkbox" id="perm${i}${j}" name="permissions[${m[0]}][]" value="${["view","create","update","delete"][j]}" ${v?"checked":""} aria-label="${m[0]} ${["voir","créer","modifier","supprimer"][j]}"></td>`).join("")}</tr>`).join(""));
const risk=[["Réservoir de La Soukra","Âge 39 ans · fissures radier",74],["Station Ariana Nord","2 pannes pompe en 60 j",68],["Conduite fonte Bab Saadoun","Fonte grise 1962 · 9 fuites/an",61],["Conduite DN 600 Ben Arous","Pertes 6 m³/h",42],["Réservoir Mégrine","Inspection récente",12]];
fill("riskTable",risk.map(r=>{const c=r[2]>=60?"var(--crit)":r[2]>=35?"var(--warn)":"var(--teal)";return `<tr><td><strong>${r[0]}</strong></td><td class="muted" style="font-size:.84rem">${r[1]}</td><td><div class="risk"><div class="bar"><span style="width:${r[2]}%;background:${c}"></span></div><span class="mono" style="width:38px;text-align:right">${r[2]} %</span></div></td></tr>`}).join(""));
/* Anomaly chart */
(function(){const v=[3.2,3.3,3.1,3.0,2.9,2.8,1.4,1.2,1.3,2.6,3.0,3.1,3.2,3.1,3.0,3.1,3.2,3.3,3.2,3.1,3.0,3.1,3.2,3.2];const W=520,H=220,L=34,B=24,T=10,mx=4;const x=i=>L+i*(W-L-10)/23,y=b=>T+(H-T-B)*(1-b/mx);let s="";
for(let b=0;b<=4;b++)s+=`<line class="grid-l" x1="${L}" x2="${W-6}" y1="${y(b)}" y2="${y(b)}"/><text x="${L-8}" y="${y(b)+4}" text-anchor="end">${b}</text>`;
s+=`<path d="M${x(0)} ${y(3.6)} L${x(23)} ${y(3.6)} L${x(23)} ${y(2.5)} L${x(0)} ${y(2.5)}Z" fill="var(--water-soft)"/>`;
s+=`<path d="${v.map((b,i)=>(i?"L":"M")+x(i)+" "+y(b)).join(" ")}" fill="none" stroke="var(--water)" stroke-width="2.2"/>`;
[6,7,8].forEach(i=>s+=`<circle cx="${x(i)}" cy="${y(v[i])}" r="4.5" fill="var(--crit)" stroke="var(--surface)" stroke-width="2"/>`);
s+=`<text x="${x(8)+10}" y="${y(1.2)+4}" style="fill:var(--crit-fg)">03:00 · 1,2 bar</text>`;
[0,6,12,18,23].forEach(i=>s+=`<text x="${x(i)}" y="${H-6}" text-anchor="middle">${String(i).padStart(2,"0")}h</text>`);
fill("anomChart",s)})();

/* Menus déroulants */
const nd=$("notifDrop"),md=$("meDrop"),sb=$("sidebar");
function closeDrops(){if(nd)nd.hidden=true;if(md)md.hidden=true}

/* Interactions */
on("burger","click",e=>{e.stopPropagation();if(sb)sb.classList.toggle("open")});
on("notifBtn","click",e=>{e.stopPropagation();if(!nd)return;const o=nd.hidden;closeDrops();nd.hidden=!o});
on("meBtn","click",e=>{e.stopPropagation();if(!md)return;const o=md.hidden;closeDrops();md.hidden=!o});
document.addEventListener("click",e=>{if(!e.target.closest(".dropdown"))closeDrops();if(sb&&!e.target.closest(".sidebar")&&!e.target.closest("#burger"))sb.classList.remove("open")});
function toast(m,err){const box=$("toasts");if(!box)return;const t=document.createElement("div");t.className="toast"+(err?" err":"");t.textContent=(err?"✕ ":"✓ ")+m;box.appendChild(t);setTimeout(()=>t.remove(),3200)}
/* Message flash rendu par Laravel (session status) : retiré comme les autres toasts */
document.querySelectorAll("#toasts .toast").forEach(t=>setTimeout(()=>t.remove(),3200));
const modal=$("modal");let pending="",pendingAction="";
document.addEventListener("click",e=>{const d=e.target.closest("[data-del]");if(d&&modal){pending=d.dataset.del;pendingAction=d.dataset.action||"";const mt=$("mText");if(mt)mt.textContent=`« ${pending} » sera supprimé définitivement, ainsi que les données qui lui sont rattachées.`;modal.hidden=false;const ok=$("mOk");if(ok)ok.focus()}
 const t=e.target.closest("[data-toast]");if(t)toast(t.dataset.toast)});
on("mCancel","click",()=>modal.hidden=true);
if(modal)modal.addEventListener("click",e=>{if(e.target===modal)modal.hidden=true});
document.addEventListener("keydown",e=>{if(e.key==="Escape"&&modal)modal.hidden=true});
on("mOk","click",()=>{const f=$("mForm");if(pendingAction&&f){f.action=pendingAction;f.submit();return}modal.hidden=true;toast(`« ${pending} » a été supprimé.`)});
const rg=$("rolesGrid");if(rg&&innerWidth<760)rg.style.gridTemplateColumns="1fr";
