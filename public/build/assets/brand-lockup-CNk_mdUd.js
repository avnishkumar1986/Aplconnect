import{r as l,j as n}from"./app-DOAS_B2b.js";/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const h=r=>r.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase(),i=(...r)=>r.filter((e,t,a)=>!!e&&e.trim()!==""&&a.indexOf(e)===t).join(" ").trim();/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var m={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor",strokeWidth:2,strokeLinecap:"round",strokeLinejoin:"round"};/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const b=l.forwardRef(({color:r="currentColor",size:e=24,strokeWidth:t=2,absoluteStrokeWidth:a,className:o="",children:s,iconNode:x,...c},p)=>l.createElement("svg",{ref:p,...m,width:e,height:e,stroke:r,strokeWidth:a?Number(t)*24/Number(e):t,className:i("lucide",o),...c},[...x.map(([d,f])=>l.createElement(d,f)),...Array.isArray(s)?s:[s]]));/**
 * @license lucide-react v0.475.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const g=(r,e)=>{const t=l.forwardRef(({className:a,...o},s)=>l.createElement(b,{ref:s,iconNode:e,className:i(`lucide-${h(r)}`,a),...o}));return t.displayName=`${r}`,t};function w({tone:r="light",compact:e=!1,orientation:t="vertical",className:a=""}){const o=r==="dark"?"text-white":"text-[#18354d]",s=r==="dark"?"text-[#8fb5ca]":"text-slate-400";return n.jsxs("div",{className:`flex min-w-0 items-center justify-center ${t==="horizontal"?`flex-row text-left ${e?"gap-2":"gap-2.5"}`:`flex-col text-center ${e?"gap-0.5":"gap-1"}`} ${a}`,children:[n.jsx("img",{src:"/apl-emblem-transparent.png",alt:"APL",className:t==="horizontal"?e?"size-10 shrink-0 object-contain":"size-12 shrink-0 object-contain":e?"size-10 shrink-0 object-contain":"size-20 shrink-0 object-contain"}),t==="horizontal"&&n.jsx("span",{className:"bg-brand-teal h-9 w-px"}),n.jsxs("span",{className:`min-w-0 ${t==="horizontal"?"after:bg-brand-gold relative pb-1 after:absolute after:bottom-0 after:left-0 after:h-px after:w-8":""}`,children:[n.jsx("span",{className:`block ${t==="horizontal"?e?"text-[13px]":"text-[15px]":e?"text-[10px]":"text-[18px]"} leading-none font-extrabold tracking-[0.06em] whitespace-nowrap ${o}`,children:"APL CONNECT"}),n.jsx("span",{className:`mt-1.5 block ${t==="horizontal"?e?"text-[8px]":"text-[9px]":e?"text-[6px]":"text-[10px]"} font-bold tracking-[0.12em] whitespace-nowrap uppercase ${s}`,children:"Enterprise Portal"})]})]})}export{w as B,g as c};
